<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Http\Requests\Api\V1\Erp\ErpWebhookRequest;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\Webhook;
use App\Support\TenantContext;
use App\Support\WebhookEventRegistry;
use App\Support\WebhookNetworkAllowlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Self-service webhook management for ERP integrations (T2a):
 * /api/v1/erp/webhooks under the erp:webhooks:manage scope. A key manages
 * only the webhooks it created; everything else is a 404.
 *
 * URLs are literal IPs so the SSRF guard never depends on DNS.
 */
class ErpWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://8.8.8.8/hooks/erp';

    private int $tenantId;

    private int $otherTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'Primary'])->id;
        $this->otherTenantId = Tenant::create(['name' => 'Other'])->id;
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    /** @return array{0: ApiKey, 1: string} */
    private function issueKey(array $scopes = [ApiScope::WebhooksManage], ?int $tenantId = null, array $extra = []): array
    {
        return ApiKey::issue(array_merge([
            'name' => 'ERP key '.uniqid(),
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $tenantId ?? $this->tenantId,
        ], $extra));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Odoo feed',
            'url' => self::URL,
            'events' => [WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED],
        ], $overrides);
    }

    private function webhookFor(?ApiKey $key, array $attrs = []): Webhook
    {
        return Webhook::factory()->create(array_merge([
            'tenant_id' => $key?->tenant_id ?? $this->tenantId,
            'api_key_id' => $key?->id,
        ], $attrs));
    }

    // ── Happy path ────────────────────────────────────────────────────────

    public function test_store_creates_an_owned_webhook_and_returns_the_secret_once(): void
    {
        [$key, $plain] = $this->issueKey();

        $response = $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/erp/webhooks', $this->payload(['headers' => ['X-Odoo-Db' => 'prod']]));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Odoo feed')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.secret');
        $secret = $response->json('secret');
        $this->assertIsString($secret);
        $this->assertSame(40, strlen($secret));

        $webhook = Webhook::withoutGlobalScopes()->findOrFail($response->json('data.id'));
        $this->assertSame($key->id, $webhook->api_key_id);
        $this->assertSame($this->tenantId, $webhook->tenant_id);
        $this->assertSame($secret, $webhook->secret);
        $this->assertSame(['X-Odoo-Db' => 'prod'], $webhook->headers);
    }

    public function test_store_accepts_a_client_supplied_secret(): void
    {
        [, $plain] = $this->issueKey();

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/erp/webhooks', $this->payload(['secret' => 'my-own-secret-123456']))
            ->assertCreated()
            ->assertJsonPath('secret', 'my-own-secret-123456');
    }

    public function test_index_and_show_never_include_the_secret(): void
    {
        [$key, $plain] = $this->issueKey();
        $own = $this->webhookFor($key, ['name' => 'Mine']);

        $index = $this->withHeader('X-Api-Key', $plain)->getJson('/api/v1/erp/webhooks');
        $index->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->assertArrayNotHasKey('secret', $index->json('data.0'));
        $this->assertArrayNotHasKey('secret', $index->json());

        $show = $this->withHeader('X-Api-Key', $plain)->getJson("/api/v1/erp/webhooks/{$own->id}");
        $show->assertOk()->assertJsonPath('data.name', 'Mine');
        $this->assertArrayNotHasKey('secret', $show->json('data'));
        $this->assertArrayNotHasKey('secret', $show->json());
    }

    public function test_patch_is_partial(): void
    {
        [$key, $plain] = $this->issueKey();
        $own = $this->webhookFor($key, ['name' => 'Keep me', 'url' => self::URL, 'secret' => 'original-secret-123456']);

        $this->withHeader('X-Api-Key', $plain)
            ->patchJson("/api/v1/erp/webhooks/{$own->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name', 'Keep me');

        $own->refresh();
        $this->assertFalse($own->is_active);
        $this->assertSame('Keep me', $own->name);
        $this->assertSame(self::URL, $own->url);
        $this->assertSame('original-secret-123456', $own->secret);
    }

    public function test_patch_can_rename_and_rotate_the_secret_without_reactivating(): void
    {
        [$key, $plain] = $this->issueKey();
        $own = $this->webhookFor($key, ['is_active' => false]);

        $this->withHeader('X-Api-Key', $plain)
            ->patchJson("/api/v1/erp/webhooks/{$own->id}", ['name' => 'Renamed', 'secret' => 'rotated-secret-1234567'])
            ->assertOk();

        $own->refresh();
        $this->assertSame('Renamed', $own->name);
        $this->assertSame('rotated-secret-1234567', $own->secret);
        $this->assertFalse($own->is_active);
        $this->assertSame($key->id, $own->api_key_id);
    }

    public function test_delete_soft_deletes(): void
    {
        [$key, $plain] = $this->issueKey();
        $own = $this->webhookFor($key);

        $this->withHeader('X-Api-Key', $plain)
            ->deleteJson("/api/v1/erp/webhooks/{$own->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('webhooks', ['id' => $own->id]);
    }

    public function test_event_types_lists_the_registry(): void
    {
        [, $plain] = $this->issueKey();

        $keys = collect($this->withHeader('X-Api-Key', $plain)
            ->getJson('/api/v1/erp/webhook-event-types')
            ->assertOk()
            ->json('data'))->pluck('key')->all();

        $this->assertEqualsCanonicalizing(WebhookEventRegistry::keys(), $keys);
    }

    // ── Authentication / authorization ────────────────────────────────────

    public function test_missing_key_is_unauthorized(): void
    {
        $this->getJson('/api/v1/erp/webhooks')->assertUnauthorized();
        $this->postJson('/api/v1/erp/webhooks', $this->payload())->assertUnauthorized();
    }

    public function test_key_without_the_scope_is_forbidden_on_every_verb(): void
    {
        [$key, $plain] = $this->issueKey([ApiScope::OrdersImport, ApiScope::ProductionRead]);
        $own = $this->webhookFor($key);

        $this->withHeader('X-Api-Key', $plain)->getJson('/api/v1/erp/webhooks')->assertForbidden();
        $this->withHeader('X-Api-Key', $plain)->getJson('/api/v1/erp/webhook-event-types')->assertForbidden();
        $this->withHeader('X-Api-Key', $plain)->getJson("/api/v1/erp/webhooks/{$own->id}")->assertForbidden();
        $this->withHeader('X-Api-Key', $plain)->postJson('/api/v1/erp/webhooks', $this->payload())->assertForbidden();
        $this->withHeader('X-Api-Key', $plain)->patchJson("/api/v1/erp/webhooks/{$own->id}", ['is_active' => false])->assertForbidden();
        $this->withHeader('X-Api-Key', $plain)->deleteJson("/api/v1/erp/webhooks/{$own->id}")->assertForbidden();

        $this->assertDatabaseCount('webhooks', 1);
        $this->assertTrue($own->fresh()->is_active);
    }

    public function test_inactive_or_expired_key_is_unauthorized(): void
    {
        [, $inactive] = $this->issueKey(extra: ['is_active' => false]);
        [, $expired] = $this->issueKey(extra: ['expires_at' => now()->subDay()]);

        $this->withHeader('X-Api-Key', $inactive)->getJson('/api/v1/erp/webhooks')->assertUnauthorized();
        $this->withHeader('X-Api-Key', $expired)->getJson('/api/v1/erp/webhooks')->assertUnauthorized();
    }

    // ── Isolation ─────────────────────────────────────────────────────────

    public function test_key_cannot_see_or_touch_admin_or_other_key_webhooks(): void
    {
        [$key, $plain] = $this->issueKey();
        [$otherKey] = $this->issueKey();
        $mine = $this->webhookFor($key, ['name' => 'Mine']);
        $admins = $this->webhookFor(null, ['name' => 'Admin hook']);
        $others = $this->webhookFor($otherKey, ['name' => 'Other key hook']);

        $ids = collect($this->withHeader('X-Api-Key', $plain)->getJson('/api/v1/erp/webhooks')->json('data'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);

        foreach ([$admins, $others] as $foreign) {
            $this->withHeader('X-Api-Key', $plain)->getJson("/api/v1/erp/webhooks/{$foreign->id}")->assertNotFound();
            $this->withHeader('X-Api-Key', $plain)
                ->patchJson("/api/v1/erp/webhooks/{$foreign->id}", ['name' => 'Hijacked', 'url' => 'not-a-url'])
                ->assertNotFound();
            $this->withHeader('X-Api-Key', $plain)->deleteJson("/api/v1/erp/webhooks/{$foreign->id}")->assertNotFound();

            $foreign->refresh();
            $this->assertNotSame('Hijacked', $foreign->name);
            $this->assertNull($foreign->deleted_at);
            $this->assertTrue($foreign->is_active);
        }
    }

    public function test_key_cannot_reach_another_tenants_webhook(): void
    {
        [, $plain] = $this->issueKey();
        [$foreignKey] = $this->issueKey(tenantId: $this->otherTenantId);
        $foreign = $this->webhookFor($foreignKey);

        $this->withHeader('X-Api-Key', $plain)->getJson("/api/v1/erp/webhooks/{$foreign->id}")->assertNotFound();
    }

    public function test_webhook_is_created_in_the_keys_tenant(): void
    {
        [, $plainA] = $this->issueKey();
        [, $plainB] = $this->issueKey(tenantId: $this->otherTenantId);

        $a = $this->withHeader('X-Api-Key', $plainA)->postJson('/api/v1/erp/webhooks', $this->payload(['name' => 'Tenant A hook']))->assertCreated();
        $b = $this->withHeader('X-Api-Key', $plainB)->postJson('/api/v1/erp/webhooks', $this->payload(['name' => 'Tenant B hook']))->assertCreated();

        $this->assertSame($this->tenantId, Webhook::withoutGlobalScopes()->find($a->json('data.id'))->tenant_id);
        $this->assertSame($this->otherTenantId, Webhook::withoutGlobalScopes()->find($b->json('data.id'))->tenant_id);

        // Names are unique per tenant, not globally.
        $this->withHeader('X-Api-Key', $plainB)->postJson('/api/v1/erp/webhooks', $this->payload(['name' => 'Tenant A hook']))->assertCreated();
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_store_requires_name_url_and_events(): void
    {
        [, $plain] = $this->issueKey();

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/erp/webhooks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'url', 'events']);
    }

    public function test_store_rejects_unknown_event_and_short_secret(): void
    {
        [, $plain] = $this->issueKey();

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/erp/webhooks', $this->payload(['events' => ['nope.event'], 'secret' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['events.0', 'secret']);
    }

    public function test_private_url_is_rejected_without_allowlist_and_accepted_with_it(): void
    {
        [, $plain] = $this->issueKey();
        $lan = $this->payload(['url' => 'http://192.168.1.50:8069/openmes/hook']);

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/erp/webhooks', $lan)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);

        WebhookNetworkAllowlist::save(['192.168.1.0/24']);

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/erp/webhooks', $lan)
            ->assertCreated();
    }

    public function test_loopback_url_is_rejected_even_with_an_allowlist(): void
    {
        [, $plain] = $this->issueKey();
        WebhookNetworkAllowlist::save(['192.168.1.0/24']);

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/erp/webhooks', $this->payload(['url' => 'http://127.0.0.1/hook']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_transport_and_signature_headers_are_rejected(): void
    {
        [, $plain] = $this->issueKey();

        foreach (['Host', 'content-length', 'Transfer-Encoding', 'X-OpenMES-Signature'] as $header) {
            $this->withHeader('X-Api-Key', $plain)
                ->postJson('/api/v1/erp/webhooks', $this->payload(['headers' => [$header => 'x']]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['headers']);
        }

        $this->assertDatabaseCount('webhooks', 0);
    }

    public function test_duplicate_name_in_the_same_tenant_is_a_422_not_a_500(): void
    {
        [$key, $plain] = $this->issueKey();
        $this->webhookFor(null, ['name' => 'Taken']);

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/erp/webhooks', $this->payload(['name' => 'Taken']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        // Renaming one's own hook to its current name is not a conflict.
        $own = $this->webhookFor($key, ['name' => 'Own']);
        $this->withHeader('X-Api-Key', $plain)
            ->patchJson("/api/v1/erp/webhooks/{$own->id}", ['name' => 'Own'])
            ->assertOk();
    }

    public function test_a_key_is_capped_at_the_maximum_number_of_webhooks(): void
    {
        [$key, $plain] = $this->issueKey();
        Webhook::factory()->count(ErpWebhookRequest::MAX_PER_KEY)->create([
            'tenant_id' => $this->tenantId,
            'api_key_id' => $key->id,
        ]);

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/erp/webhooks', $this->payload(['name' => 'One too many']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);

        // Admin-owned hooks do not count against the key.
        $this->assertSame(ErpWebhookRequest::MAX_PER_KEY, Webhook::withoutGlobalScopes()->where('api_key_id', $key->id)->count());
    }

    public function test_erp_module_off_hides_the_endpoints(): void
    {
        [, $plain] = $this->issueKey();
        \App\Support\ModuleRegistry::save(['reports']);

        $this->withHeader('X-Api-Key', $plain)->getJson('/api/v1/erp/webhooks')->assertNotFound();
    }
}
