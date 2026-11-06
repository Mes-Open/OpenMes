<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\Batch;
use App\Models\Issue;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\WorkOrder;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\WebhookEventRegistry;
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
        $this->freezeTime();
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

        $now = now()->getTimestamp();
        Http::assertSent(function ($request) use ($delivery, $now) {
            $timestamp = $request->header('X-OpenMES-Timestamp')[0] ?? null;
            if (! is_numeric($timestamp) || abs((int) $timestamp - $now) > 5) {
                return false;
            }
            // Receiver-side verification: HMAC over "{timestamp}.{raw body}".
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'shhh-this-is-the-secret');

            return hash_equals($expected, $request->header('X-OpenMES-Signature')[0] ?? '')
                && $request->hasHeader('X-OpenMES-Event', $delivery->event_type)
                && $request->hasHeader('X-OpenMES-Delivery', (string) $delivery->id);
        });

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_SUCCESS, $delivery->status);
        $this->assertSame(200, $delivery->response_code);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame($now, $delivery->signature_timestamp);
        $this->assertSame(WebhookDelivery::SIGNATURE_V1, $delivery->signature_scheme);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNotNull($webhook->fresh()->last_triggered_at);
    }

    public function test_signature_does_not_verify_with_a_different_timestamp(): void
    {
        Http::fake(fn () => Http::response('ok', 200));

        $webhook = Webhook::factory()->create(['url' => 'https://8.8.8.8/hook', 'secret' => 'shhh-this-is-the-secret']);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        (new DeliverWebhookJob($delivery->id))->handle();

        Http::assertSent(function ($request) {
            $timestamp = (int) $request->header('X-OpenMES-Timestamp')[0];
            $sent = $request->header('X-OpenMES-Signature')[0];

            // A replay with a forged (fresh) timestamp no longer matches, and the
            // body-only HMAC no longer verifies either.
            $forged = 'sha256='.hash_hmac('sha256', ($timestamp + 1).'.'.$request->body(), 'shhh-this-is-the-secret');
            $bodyOnly = 'sha256='.hash_hmac('sha256', $request->body(), 'shhh-this-is-the-secret');

            return ! hash_equals($forged, $sent) && ! hash_equals($bodyOnly, $sent);
        });
    }

    public function test_legacy_webhook_signs_body_only(): void
    {
        Http::fake(fn () => Http::response('ok', 200));

        $webhook = Webhook::factory()->legacySignature()->create([
            'url' => 'https://8.8.8.8/hook',
            'secret' => 'shhh-this-is-the-secret',
        ]);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        (new DeliverWebhookJob($delivery->id))->handle();

        Http::assertSent(function ($request) {
            $expected = 'sha256='.hash_hmac('sha256', $request->body(), 'shhh-this-is-the-secret');

            return $request->hasHeader('X-OpenMES-Signature', $expected)
                && is_numeric($request->header('X-OpenMES-Timestamp')[0] ?? null);
        });

        $this->assertSame(WebhookDelivery::SIGNATURE_LEGACY, $delivery->fresh()->signature_scheme);
    }

    public function test_custom_headers_cannot_override_signature_headers(): void
    {
        Http::fake(fn () => Http::response('ok', 200));

        $webhook = Webhook::factory()->create([
            'url' => 'https://8.8.8.8/hook',
            'secret' => 'shhh-this-is-the-secret',
            'headers' => ['X-OpenMES-Timestamp' => '1', 'X-OpenMES-Signature' => 'sha256=forged'],
        ]);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        (new DeliverWebhookJob($delivery->id))->handle();

        Http::assertSent(fn ($request) => $request->header('X-OpenMES-Timestamp')[0] !== '1'
            && $request->header('X-OpenMES-Signature')[0] !== 'sha256=forged');
    }

    public function test_migration_switches_existing_webhooks_to_legacy_signature(): void
    {
        $migration = require database_path('migrations/2026_11_06_100000_add_legacy_signature_to_webhooks_table.php');
        $migration->down();

        $id = DB::table('webhooks')->insertGetId([
            'name' => 'Pre-existing ERP hook',
            'url' => 'https://8.8.8.8/hook',
            'secret' => encrypt('x', false),
            'events' => json_encode([WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertTrue((bool) DB::table('webhooks')->where('id', $id)->value('legacy_signature'));
        // New rows default to the timestamped scheme.
        $this->assertFalse(Webhook::factory()->create()->fresh()->legacy_signature);
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
}
