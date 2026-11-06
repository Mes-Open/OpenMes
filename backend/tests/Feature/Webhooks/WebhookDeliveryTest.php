<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\ApiKey;
use App\Models\Batch;
use App\Models\Issue;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\WorkOrder;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\WebhookEventRegistry;
use App\Support\WebhookNetworkAllowlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Delivery pipeline for outgoing webhooks (#20): fan-out from the dispatcher
 * and model observers, HMAC signing, SSRF guard at delivery time, and the
 * retry/backoff contract.
 */
class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_records_pending_delivery_and_queues_job(): void
    {
        Bus::fake();

        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED])->create();

        app(WebhookDispatcher::class)->dispatch(
            WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED,
            ['id' => 1, 'status' => 'DONE'],
        );

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $webhook->id,
            'event_type' => WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED,
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);
        Bus::assertDispatched(DeliverWebhookJob::class);
    }

    public function test_inactive_webhook_is_not_dispatched(): void
    {
        Bus::fake();
        Webhook::factory()->inactive()->subscribedTo([WebhookEventRegistry::ISSUE_CREATED])->create();

        app(WebhookDispatcher::class)->dispatch(WebhookEventRegistry::ISSUE_CREATED, ['id' => 1]);

        $this->assertDatabaseCount('webhook_deliveries', 0);
        Bus::assertNotDispatched(DeliverWebhookJob::class);
    }

    public function test_unsubscribed_event_is_skipped(): void
    {
        Bus::fake();
        Webhook::factory()->subscribedTo([WebhookEventRegistry::ISSUE_CREATED])->create();

        app(WebhookDispatcher::class)->dispatch(WebhookEventRegistry::BATCH_COMPLETED, ['id' => 1]);

        $this->assertDatabaseCount('webhook_deliveries', 0);
        Bus::assertNotDispatched(DeliverWebhookJob::class);
    }

    public function test_work_order_status_change_fires_webhook(): void
    {
        Bus::fake();
        Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED])->create();

        $workOrder = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);
        $workOrder->update(['status' => WorkOrder::STATUS_DONE]);

        $this->assertDatabaseHas('webhook_deliveries', [
            'event_type' => WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED,
        ]);
        Bus::assertDispatched(DeliverWebhookJob::class);
    }

    public function test_issue_created_fires_webhook(): void
    {
        Bus::fake();
        Webhook::factory()->subscribedTo([WebhookEventRegistry::ISSUE_CREATED])->create();

        Issue::factory()->create();

        $this->assertDatabaseHas('webhook_deliveries', [
            'event_type' => WebhookEventRegistry::ISSUE_CREATED,
        ]);
        Bus::assertDispatched(DeliverWebhookJob::class);
    }

    public function test_batch_completed_fires_webhook(): void
    {
        Bus::fake();
        Webhook::factory()->subscribedTo([WebhookEventRegistry::BATCH_COMPLETED])->create();

        $batch = Batch::factory()->create(['status' => Batch::STATUS_IN_PROGRESS]);
        $batch->update(['status' => Batch::STATUS_DONE]);

        $this->assertDatabaseHas('webhook_deliveries', [
            'event_type' => WebhookEventRegistry::BATCH_COMPLETED,
        ]);
        Bus::assertDispatched(DeliverWebhookJob::class);
    }

    public function test_delivery_sends_valid_hmac_signature(): void
    {
        Http::fake(fn () => Http::response('ok', 200));

        $webhook = Webhook::factory()->create([
            'url' => 'https://8.8.8.8/hook',
            'secret' => 'shhh-this-is-the-secret',
        ]);
        $delivery = WebhookDelivery::factory()->create([
            'webhook_id' => $webhook->id,
            'event_type' => WebhookEventRegistry::ISSUE_CREATED,
        ]);

        (new DeliverWebhookJob($delivery->id))->handle();

        Http::assertSent(function ($request) use ($delivery) {
            $expected = 'sha256='.hash_hmac('sha256', $request->body(), 'shhh-this-is-the-secret');

            return $request->hasHeader('X-OpenMES-Signature', $expected)
                && $request->hasHeader('X-OpenMES-Event', $delivery->event_type)
                && $request->hasHeader('X-OpenMES-Delivery', (string) $delivery->id);
        });

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_SUCCESS, $delivery->status);
        $this->assertSame(200, $delivery->response_code);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNotNull($webhook->fresh()->last_triggered_at);
    }

    public function test_non_2xx_response_throws_to_retry_and_records_attempt(): void
    {
        Http::fake(fn () => Http::response('boom', 500));

        $webhook = Webhook::factory()->create(['url' => 'https://8.8.8.8/hook']);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        try {
            (new DeliverWebhookJob($delivery->id))->handle();
            $this->fail('Expected the job to throw on a non-2xx response.');
        } catch (\RuntimeException $e) {
            // expected — Laravel would release the job for a backed-off retry.
        }

        $delivery->refresh();
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(500, $delivery->response_code);
        $this->assertNotSame(WebhookDelivery::STATUS_SUCCESS, $delivery->status);
    }

    public function test_failed_hook_records_failed_status(): void
    {
        $webhook = Webhook::factory()->create(['url' => 'https://8.8.8.8/hook']);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        (new DeliverWebhookJob($delivery->id))->failed(new \RuntimeException('Connection refused'));

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->status);
        $this->assertStringContainsString('Connection refused', (string) $delivery->error);
    }

    public function test_ssrf_url_is_blocked_at_delivery_time(): void
    {
        Http::fake();

        // A loopback URL bypassing save-time validation (factory) is still
        // refused when the job runs.
        $webhook = Webhook::factory()->create(['url' => 'http://127.0.0.1/hook']);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        (new DeliverWebhookJob($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->status);
        Http::assertNothingSent();
    }

    public function test_retry_and_backoff_contract(): void
    {
        $job = new DeliverWebhookJob(1);

        $this->assertSame(5, $job->tries);
        $this->assertSame([10, 30, 60, 300, 900], $job->backoff());
    }

    public function test_secret_is_encrypted_at_rest(): void
    {
        $webhook = Webhook::factory()->create(['secret' => 'plaintext-secret-value']);

        $raw = DB::table('webhooks')->where('id', $webhook->id)->value('secret');

        $this->assertNotSame('plaintext-secret-value', $raw); // stored ciphertext
        $this->assertSame('plaintext-secret-value', $webhook->fresh()->secret); // decrypts via cast
    }

    // ── Private-network allowlist + key ownership at delivery time (T2a) ──

    public function test_allowlisted_private_receiver_is_delivered_and_pinned(): void
    {
        Http::fake(fn () => Http::response('ok', 200));
        WebhookNetworkAllowlist::save(['192.168.1.0/24']);

        $webhook = Webhook::factory()->create(['url' => 'http://192.168.1.50:8069/openmes/hook']);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        (new DeliverWebhookJob($delivery->id))->handle();

        $this->assertSame(WebhookDelivery::STATUS_SUCCESS, $delivery->fresh()->status);
        Http::assertSent(fn ($request) => $request->url() === 'http://192.168.1.50:8069/openmes/hook');
    }

    public function test_allowlist_is_rechecked_at_delivery_time(): void
    {
        Http::fake();
        WebhookNetworkAllowlist::save(['192.168.1.0/24']);
        $webhook = Webhook::factory()->create(['url' => 'http://192.168.1.50:8069/openmes/hook']);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        // The admin narrows the list after the webhook was saved.
        WebhookNetworkAllowlist::save([]);

        (new DeliverWebhookJob($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->status);
        $this->assertStringContainsString('private/reserved', (string) $delivery->error);
        Http::assertNothingSent();
    }

    public function test_loopback_is_blocked_at_delivery_even_when_listed(): void
    {
        Http::fake();
        WebhookNetworkAllowlist::save(['127.0.0.0/8']); // bypassing validation on purpose

        $webhook = Webhook::factory()->create(['url' => 'http://127.0.0.1/hook']);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        (new DeliverWebhookJob($delivery->id))->handle();

        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->fresh()->status);
        Http::assertNothingSent();
    }

    /** @return array<string, array{0: array<string, mixed>, 1: bool}> */
    public static function unusableKeys(): array
    {
        return [
            'deactivated' => [['is_active' => false], false],
            'expired' => [['expires_at' => now()->subDay()], false],
            'deleted' => [[], true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusableKeys')]
    public function test_webhook_of_an_unusable_key_is_not_delivered(array $state, bool $trash): void
    {
        Http::fake();
        $key = ApiKey::factory()->create();
        $webhook = Webhook::factory()->create(['url' => 'https://8.8.8.8/hook', 'api_key_id' => $key->id]);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        // Write the key state quietly so the model events (which would already
        // deactivate the hook) don't fire — the job must hold on its own.
        $key->forceFill($state)->saveQuietly();
        if ($trash) {
            ApiKey::withoutEvents(fn () => $key->delete());
        }

        (new DeliverWebhookJob($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame('Owning API key is no longer active.', $delivery->error);
        Http::assertNothingSent();
    }

    public function test_webhook_of_a_usable_key_is_delivered(): void
    {
        Http::fake(fn () => Http::response('ok', 200));
        $key = ApiKey::factory()->create();
        $webhook = Webhook::factory()->create(['url' => 'https://8.8.8.8/hook', 'api_key_id' => $key->id]);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        (new DeliverWebhookJob($delivery->id))->handle();

        $this->assertSame(WebhookDelivery::STATUS_SUCCESS, $delivery->fresh()->status);
    }

    public function test_redirects_are_not_followed(): void
    {
        // A receiver answering 302 → cloud metadata must not get the request
        // bounced there: the Location target was never vetted or pinned.
        Http::fake([
            'https://8.8.8.8/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
            '*' => Http::response('metadata', 200),
        ]);

        $webhook = Webhook::factory()->create(['url' => 'https://8.8.8.8/hook']);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        try {
            (new DeliverWebhookJob($delivery->id))->handle();
            $this->fail('Expected a 3xx to be treated as a failed attempt.');
        } catch (\RuntimeException $e) {
            // expected — retried like any other non-2xx.
        }

        $delivery->refresh();
        $this->assertSame(302, $delivery->response_code);
        $this->assertNotSame(WebhookDelivery::STATUS_SUCCESS, $delivery->status);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
    }
}
