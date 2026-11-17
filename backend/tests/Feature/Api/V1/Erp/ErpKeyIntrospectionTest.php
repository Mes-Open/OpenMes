<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /api/v1/erp/me` — key introspection: an integration learns what its own
 * key may do (and what it is missing) without ever seeing secret material.
 */
class ErpKeyIntrospectionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Odoo Tenant']);
        app(TenantContext::class)->set($this->tenant->id);
    }

    /** @return array{0: ApiKey, 1: string} */
    private function issue(array $scopes, array $overrides = []): array
    {
        return ApiKey::issue(array_merge([
            'name' => 'Odoo connector',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $this->tenant->id,
        ], $overrides));
    }

    public function test_me_returns_key_metadata_for_a_single_scope_key(): void
    {
        [, $plaintext] = $this->issue([ApiScope::ProductionRead]);

        $response = $this->withHeader('X-Api-Key', $plaintext)->getJson('/api/v1/erp/me');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Odoo connector')
            ->assertJsonPath('data.scopes', ['erp:production:read'])
            ->assertJsonPath('data.tenant.id', $this->tenant->id)
            ->assertJsonPath('data.tenant.name', 'Odoo Tenant')
            ->assertJsonPath('data.expires_at', null)
            ->assertJsonPath('data.modules.erp', true)
            ->assertJsonPath('data.app_version', config('version.current'));

        $missing = $response->json('data.missing_scopes');
        $this->assertContains('erp:masterdata:read', $missing);
        $this->assertNotContains('erp:production:read', $missing);
        $this->assertCount(count(ApiScope::cases()) - 1, $missing);
    }

    public function test_me_reflects_multiple_scopes_and_expiry(): void
    {
        $expires = now()->addMonth()->startOfSecond();
        [, $plaintext] = $this->issue(
            [ApiScope::MasterDataRead, ApiScope::ProductionRead, ApiScope::StockRead],
            ['expires_at' => $expires],
        );

        $response = $this->withHeader('X-Api-Key', $plaintext)->getJson('/api/v1/erp/me');

        $response->assertOk()
            ->assertJsonPath('data.expires_at', $expires->toIso8601String());
        $this->assertEqualsCanonicalizing(
            ['erp:masterdata:read', 'erp:production:read', 'erp:stock:read'],
            $response->json('data.scopes'),
        );
        $this->assertEqualsCanonicalizing(
            array_diff(ApiScope::values(), ['erp:masterdata:read', 'erp:production:read', 'erp:stock:read']),
            $response->json('data.missing_scopes'),
        );
    }

    public function test_me_needs_no_scope_and_reports_everything_missing_for_an_empty_key(): void
    {
        [, $plaintext] = $this->issue([]);

        $this->withHeader('X-Api-Key', $plaintext)->getJson('/api/v1/erp/me')
            ->assertOk()
            ->assertJsonPath('data.scopes', [])
            ->assertJsonPath('data.missing_scopes', ApiScope::values());
    }

    public function test_me_never_exposes_secret_material(): void
    {
        [$key, $plaintext] = $this->issue([ApiScope::ProductionRead], ['ip_allowlist' => ['127.0.0.1']]);

        $response = $this->withHeader('X-Api-Key', $plaintext)->getJson('/api/v1/erp/me');

        $response->assertOk()
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.key_prefix')
            ->assertJsonMissingPath('data.key_hash')
            ->assertJsonMissingPath('data.ip_allowlist')
            ->assertJsonMissingPath('data.last_used_at');

        $body = $response->getContent();
        $this->assertStringNotContainsString($key->key_prefix, $body);
        $this->assertStringNotContainsString($key->key_hash, $body);
        $this->assertStringNotContainsString($plaintext, $body);
    }

    public function test_me_reports_the_warehouse_module_state(): void
    {
        [, $plaintext] = $this->issue([ApiScope::ProductionRead]);

        ModuleRegistry::save(['erp', 'warehouse']);
        $this->withHeader('X-Api-Key', $plaintext)->getJson('/api/v1/erp/me')
            ->assertOk()
            ->assertJsonPath('data.modules.warehouse', true);

        ModuleRegistry::save(['erp']);
        $this->withHeader('X-Api-Key', $plaintext)->getJson('/api/v1/erp/me')
            ->assertOk()
            ->assertJsonPath('data.modules.erp', true)
            ->assertJsonPath('data.modules.warehouse', false);
    }

    public function test_me_requires_a_key(): void
    {
        $this->getJson('/api/v1/erp/me')->assertStatus(401);
    }

    public function test_me_rejects_an_unknown_key(): void
    {
        $this->withHeader('X-Api-Key', ApiKey::PREFIX.'not-a-real-key')
            ->getJson('/api/v1/erp/me')
            ->assertStatus(401);
    }

    public function test_me_rejects_an_inactive_key(): void
    {
        [, $plaintext] = $this->issue([ApiScope::ProductionRead], ['is_active' => false]);

        $this->withHeader('X-Api-Key', $plaintext)->getJson('/api/v1/erp/me')->assertStatus(401);
    }

    public function test_me_rejects_an_expired_key(): void
    {
        [, $plaintext] = $this->issue([ApiScope::ProductionRead], ['expires_at' => now()->subMinute()]);

        $this->withHeader('X-Api-Key', $plaintext)->getJson('/api/v1/erp/me')->assertStatus(401);
    }

    public function test_me_404s_when_the_erp_module_is_disabled(): void
    {
        [, $plaintext] = $this->issue([ApiScope::ProductionRead]);
        ModuleRegistry::save(['reports']);

        $this->withHeader('X-Api-Key', $plaintext)->getJson('/api/v1/erp/me')->assertStatus(404);
    }
}
