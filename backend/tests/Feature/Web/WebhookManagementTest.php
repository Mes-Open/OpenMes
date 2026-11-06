<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Webhook;
use App\Support\WebhookEventRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin CRUD for outgoing webhook endpoints (#20): access control, validation
 * (including SSRF-guarded URLs), secret handling and soft delete.
 *
 * URLs use literal public/loopback IPs so the SSRF guard never depends on DNS.
 */
class WebhookManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private const VALID_URL = 'https://8.8.8.8/hooks/openmes';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_webhooks(): void
    {
        $this->actingAs($this->admin)->get('/admin/webhooks')->assertOk();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/webhooks')->assertRedirect();
    }

    public function test_non_admin_without_tab_permission_is_forbidden(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get('/admin/webhooks')->assertForbidden();
    }

    public function test_store_creates_webhook_with_auto_generated_secret(): void
    {
        $this->actingAs($this->admin)->post('/admin/webhooks', [
            'name' => 'Slack alerts',
            'url' => self::VALID_URL,
            'events' => [WebhookEventRegistry::ISSUE_CREATED],
            'is_active' => true,
        ])->assertRedirect('/admin/webhooks');

        $webhook = Webhook::firstWhere('name', 'Slack alerts');
        $this->assertNotNull($webhook);
        $this->assertSame([WebhookEventRegistry::ISSUE_CREATED], $webhook->events);
        $this->assertNotEmpty($webhook->secret); // auto-generated
    }

    public function test_store_validation_rejects_missing_events_and_bad_url(): void
    {
        $this->actingAs($this->admin)->post('/admin/webhooks', [
            'name' => 'Broken',
            'url' => 'not-a-url',
            'events' => [],
        ])->assertSessionHasErrors(['url', 'events']);
    }

    public function test_store_rejects_unknown_event(): void
    {
        $this->actingAs($this->admin)->post('/admin/webhooks', [
            'name' => 'Bad event',
            'url' => self::VALID_URL,
            'events' => ['totally.made.up'],
        ])->assertSessionHasErrors('events.0');
    }

    public function test_store_rejects_ssrf_url(): void
    {
        foreach (['http://127.0.0.1/x', 'http://169.254.169.254/latest/meta-data', 'http://10.0.0.5/x'] as $url) {
            $this->actingAs($this->admin)->post('/admin/webhooks', [
                'name' => 'SSRF '.$url,
                'url' => $url,
                'events' => [WebhookEventRegistry::ISSUE_CREATED],
            ])->assertSessionHasErrors('url');
        }

        $this->assertSame(0, Webhook::count());
    }

    public function test_update_keeps_existing_secret_when_blank(): void
    {
        $webhook = Webhook::factory()->create(['secret' => 'original-secret-value-1234']);

        $this->actingAs($this->admin)->put("/admin/webhooks/{$webhook->id}", [
            'name' => 'Renamed',
            'url' => self::VALID_URL,
            'events' => [WebhookEventRegistry::BATCH_COMPLETED],
            'secret' => '', // blank → keep
            'is_active' => true,
        ])->assertRedirect('/admin/webhooks');

        $webhook->refresh();
        $this->assertSame('Renamed', $webhook->name);
        $this->assertSame('original-secret-value-1234', $webhook->secret);
        $this->assertSame([WebhookEventRegistry::BATCH_COMPLETED], $webhook->events);
    }

    public function test_update_omitting_is_active_keeps_current_state(): void
    {
        $webhook = Webhook::factory()->inactive()->create();

        // An update payload without is_active must NOT silently re-activate it.
        $this->actingAs($this->admin)->put("/admin/webhooks/{$webhook->id}", [
            'name' => 'Still disabled',
            'url' => self::VALID_URL,
            'events' => [WebhookEventRegistry::ISSUE_CREATED],
        ])->assertRedirect('/admin/webhooks');

        $this->assertFalse($webhook->fresh()->is_active);
    }

    public function test_destroy_soft_deletes(): void
    {
        $webhook = Webhook::factory()->create();

        $this->actingAs($this->admin)->delete("/admin/webhooks/{$webhook->id}")
            ->assertRedirect('/admin/webhooks');

        $this->assertSoftDeleted('webhooks', ['id' => $webhook->id]);
    }

    public function test_toggle_active(): void
    {
        $webhook = Webhook::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)->post("/admin/webhooks/{$webhook->id}/toggle-active")
            ->assertRedirect('/admin/webhooks');

        $this->assertFalse($webhook->fresh()->is_active);
    }

    public function test_secret_is_never_synced(): void
    {
        $registry = new \App\Sync\ShapeRegistry;
        $prop = (new \ReflectionObject($registry))->getProperty('shapes');
        $prop->setAccessible(true);
        $shapes = $prop->getValue($registry);

        $this->assertArrayHasKey('webhooks', $shapes);
        $this->assertNotContains('secret', $shapes['webhooks']['columns']);
    }

    public function test_store_defaults_to_timestamped_signature(): void
    {
        $this->actingAs($this->admin)->post('/admin/webhooks', [
            'name' => 'New ERP hook',
            'url' => self::VALID_URL,
            'events' => [WebhookEventRegistry::WORK_ORDER_PROGRESS, WebhookEventRegistry::STOCK_DOCUMENT_POSTED],
        ])->assertRedirect('/admin/webhooks')->assertSessionHasNoErrors();

        $webhook = Webhook::firstWhere('name', 'New ERP hook');
        $this->assertFalse($webhook->legacy_signature);
        $this->assertSame([WebhookEventRegistry::WORK_ORDER_PROGRESS, WebhookEventRegistry::STOCK_DOCUMENT_POSTED], $webhook->events);
    }

    public function test_store_can_opt_into_legacy_signature(): void
    {
        $this->actingAs($this->admin)->post('/admin/webhooks', [
            'name' => 'Old receiver',
            'url' => self::VALID_URL,
            'events' => [WebhookEventRegistry::ISSUE_CREATED],
            'legacy_signature' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Webhook::firstWhere('name', 'Old receiver')->legacy_signature);
    }

    public function test_update_keeps_legacy_signature_when_omitted_and_can_turn_it_off(): void
    {
        $webhook = Webhook::factory()->legacySignature()->create();
        $base = ['name' => $webhook->name, 'url' => self::VALID_URL, 'events' => [WebhookEventRegistry::ISSUE_CREATED]];

        $this->actingAs($this->admin)->put("/admin/webhooks/{$webhook->id}", $base)->assertSessionHasNoErrors();
        $this->assertTrue($webhook->fresh()->legacy_signature);

        $this->actingAs($this->admin)->put("/admin/webhooks/{$webhook->id}", $base + ['legacy_signature' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($webhook->fresh()->legacy_signature);
    }

    public function test_legacy_signature_must_be_boolean(): void
    {
        $this->actingAs($this->admin)->post('/admin/webhooks', [
            'name' => 'Bad flag',
            'url' => self::VALID_URL,
            'events' => [WebhookEventRegistry::ISSUE_CREATED],
            'legacy_signature' => 'abc',
        ])->assertSessionHasErrors('legacy_signature');

        $this->assertNull(Webhook::firstWhere('name', 'Bad flag'));
    }

    public function test_guest_and_operator_cannot_store_or_update(): void
    {
        $webhook = Webhook::factory()->create();
        $payload = ['name' => 'x', 'url' => self::VALID_URL, 'events' => [WebhookEventRegistry::ISSUE_CREATED], 'legacy_signature' => true];

        $this->post('/admin/webhooks', $payload)->assertRedirect();
        $this->put("/admin/webhooks/{$webhook->id}", $payload)->assertRedirect();

        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $this->actingAs($operator)->post('/admin/webhooks', $payload)->assertForbidden();
        $this->actingAs($operator)->put("/admin/webhooks/{$webhook->id}", $payload)->assertForbidden();

        $this->assertFalse($webhook->fresh()->legacy_signature);
    }

    public function test_edit_page_exposes_legacy_flag_and_new_events(): void
    {
        $webhook = Webhook::factory()->legacySignature()->create();

        $this->actingAs($this->admin)->get("/admin/webhooks/{$webhook->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/webhooks/Edit')
                ->where('webhook.legacy_signature', true)
                ->where('events', fn ($events) => collect($events)->pluck('key')->intersect([
                    WebhookEventRegistry::WORK_ORDER_CREATED,
                    WebhookEventRegistry::WORK_ORDER_PROGRESS,
                    WebhookEventRegistry::QUALITY_ISSUE_CREATED,
                    WebhookEventRegistry::STOCK_DOCUMENT_POSTED,
                ])->count() === 4));
    }

    public function test_signature_columns_are_synced_for_the_ui(): void
    {
        $registry = new \App\Sync\ShapeRegistry;
        $prop = (new \ReflectionObject($registry))->getProperty('shapes');
        $prop->setAccessible(true);
        $shapes = $prop->getValue($registry);

        $this->assertContains('legacy_signature', $shapes['webhooks']['columns']);
        $this->assertContains('signature_timestamp', $shapes['webhook_deliveries']['columns']);
        $this->assertContains('signature_scheme', $shapes['webhook_deliveries']['columns']);
    }
}
