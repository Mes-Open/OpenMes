<?php

namespace Tests\Feature\Modules;

use App\Models\IntegrationConfig;
use App\Models\Material;
use App\Models\StockDocument;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Pantheon\Providers\PantheonServiceProvider;
use Modules\Pantheon\Services\PantheonSettings;
use Modules\Pantheon\Sync\PushStockDocuments;
use Tests\TestCase;

/**
 * Regression cover for the two security findings on the Pantheon connector.
 *
 * The module is normally enabled through Admin → Modules, but its classes are
 * autoloaded either way (`Modules\` is a PSR-4 root), so these tests exercise them
 * directly instead of depending on module state.
 */
class PantheonConnectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The module is disabled by default, so its provider is not booted in tests.
        // Register the real one rather than re-declaring its bindings here: the
        // per-tenant (non-singleton) binding is part of what these tests verify.
        $this->app->register(PantheonServiceProvider::class);

        // Nothing here may reach a real PAWS instance.
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();

        parent::tearDown();
    }

    private function settings(array $config, bool $active = true): PantheonSettings
    {
        return new PantheonSettings($config, $active);
    }

    // ── Finding 1: tenant isolation ─────────────────────────────────────────

    public function test_pushing_documents_only_touches_the_active_tenants_documents(): void
    {
        $ours = Tenant::create(['name' => 'Our customer'])->id;
        $theirs = Tenant::create(['name' => 'Another customer'])->id;

        $ourDocument = $this->postedDocumentFor($ours, 'MI/2026/0001');
        $theirDocument = $this->postedDocumentFor($theirs, 'MI/2026/0002');

        Http::fake([
            '*/api/Users/authwithtoken' => Http::response(['token' => 'test-token']),
            '*/api/Move/insert' => Http::response(['acKey' => 'RW-1']),
            '*/api/Move/changedocstatus/*' => Http::response([]),
        ]);

        // The command sets the context per tenant; this is that context.
        app(TenantContext::class)->set($ours);

        $report = $this->pushFor($ours);

        $this->assertSame(1, $report['imported'], 'exactly one document should have been booked');

        // Ours is acknowledged, theirs is untouched — the whole point of the fix.
        $this->assertNotNull($ourDocument->fresh()->erp_synced_at);
        $this->assertNull($theirDocument->fresh()->erp_synced_at);
        $this->assertSame('RW-1', $ourDocument->fresh()->erp_reference);
    }

    public function test_without_a_tenant_context_the_query_would_span_tenants(): void
    {
        // Documents the connector must never mix. Guards the assumption the fix
        // rests on: TenantScope does not filter when no tenant can be resolved, so
        // the command — not the scope — is responsible for the boundary.
        $a = Tenant::create(['name' => 'A'])->id;
        $b = Tenant::create(['name' => 'B'])->id;
        $this->postedDocumentFor($a, 'MI/2026/0003');
        $this->postedDocumentFor($b, 'MI/2026/0004');

        app(TenantContext::class)->clear();
        $this->assertSame(2, StockDocument::posted()->notSynced()->count());

        app(TenantContext::class)->set($a);
        $this->assertSame(1, StockDocument::posted()->notSynced()->count());
    }

    public function test_settings_are_resolved_per_tenant_not_cached_process_wide(): void
    {
        $a = Tenant::create(['name' => 'A'])->id;
        $b = Tenant::create(['name' => 'B'])->id;

        foreach ([[$a, 'https://paws-a.local', 'DB_A'], [$b, 'https://paws-b.local', 'DB_B']] as [$tenant, $url, $db]) {
            IntegrationConfig::create([
                'system_type' => PantheonSettings::SYSTEM_TYPE,
                'system_name' => 'Datalab Pantheon',
                'api_config' => ['base_url' => $url, 'username' => 'openmes', 'password' => 'secret', 'company_db' => $db],
                'is_active' => true,
                'tenant_id' => $tenant,
            ]);
        }

        app(TenantContext::class)->set($a);
        $this->assertSame('DB_A', app(PantheonSettings::class)->companyDb());

        // A singleton binding would hand tenant B tenant A's credentials here.
        app(TenantContext::class)->set($b);
        $this->assertSame('DB_B', app(PantheonSettings::class)->companyDb());
    }

    // ── Finding 2: cleartext transport ──────────────────────────────────────

    public function test_a_plain_http_paws_address_is_refused(): void
    {
        $settings = $this->settings([
            'base_url' => 'http://paws.plant.local',
            'username' => 'openmes',
            'password' => 'secret',
            'company_db' => 'DEMO',
        ]);

        $this->assertTrue($settings->isConfigured());
        $this->assertNotNull($settings->transportProblem(), 'http:// must be reported as a problem');
        $this->assertStringContainsString('http://', $settings->transportProblem());
    }

    public function test_plain_http_is_allowed_only_when_explicitly_accepted(): void
    {
        $settings = $this->settings([
            'base_url' => 'http://paws.plant.local',
            'username' => 'openmes',
            'password' => 'secret',
            'company_db' => 'DEMO',
            'allow_insecure_http' => true,
        ]);

        $this->assertNull($settings->transportProblem());
    }

    public function test_https_needs_no_opt_in_and_a_bad_scheme_is_refused(): void
    {
        $base = ['username' => 'openmes', 'password' => 'secret', 'company_db' => 'DEMO'];

        $this->assertNull($this->settings([...$base, 'base_url' => 'https://paws.plant.local'])->transportProblem());
        $this->assertNotNull($this->settings([...$base, 'base_url' => 'ftp://paws.plant.local'])->transportProblem());
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function postedDocumentFor(int $tenantId, string $number): StockDocument
    {
        app(TenantContext::class)->set($tenantId);

        $warehouse = Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-'.$tenantId]);
        $material = Material::factory()->create(['code' => 'M-'.$tenantId]);

        $document = StockDocument::factory()->posted()->create([
            'document_no' => $number,
            'warehouse_id' => $warehouse->id,
        ]);
        $document->lines()->create(['material_id' => $material->id, 'quantity' => 5, 'unit_of_measure' => 'kg']);

        app(TenantContext::class)->clear();

        return $document;
    }

    /** @return array{imported: int, updated: int, skipped: int, errors: array<int, mixed>} */
    private function pushFor(int $tenantId): array
    {
        IntegrationConfig::create([
            'system_type' => PantheonSettings::SYSTEM_TYPE,
            'system_name' => 'Datalab Pantheon',
            'api_config' => [
                'base_url' => 'https://paws.plant.local',
                'username' => 'openmes',
                'password' => 'secret',
                'company_db' => 'DEMO',
                'document_types' => ['material_issue' => 'RW', 'product_receipt' => 'PW', 'posted_status' => 'P'],
            ],
            'is_active' => true,
            'tenant_id' => $tenantId,
        ]);

        return app(PushStockDocuments::class)->run();
    }
}
