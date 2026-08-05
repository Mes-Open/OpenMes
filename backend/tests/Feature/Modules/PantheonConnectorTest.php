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
use Modules\Pantheon\Sync\SyncProducts;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $ours = Tenant::factory()->create()->id;
        $theirs = Tenant::factory()->create()->id;

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
        $a = Tenant::factory()->create()->id;
        $b = Tenant::factory()->create()->id;
        $this->postedDocumentFor($a, 'MI/2026/0003');
        $this->postedDocumentFor($b, 'MI/2026/0004');

        app(TenantContext::class)->clear();
        $this->assertSame(2, StockDocument::posted()->notSynced()->count());

        app(TenantContext::class)->set($a);
        $this->assertSame(1, StockDocument::posted()->notSynced()->count());
    }

    public function test_settings_are_resolved_per_tenant_not_cached_process_wide(): void
    {
        $a = Tenant::factory()->create()->id;
        $b = Tenant::factory()->create()->id;

        foreach ([[$a, 'DB_A'], [$b, 'DB_B']] as [$tenant, $db]) {
            IntegrationConfig::factory()
                ->pantheon(['company_db' => $db])
                ->create(['tenant_id' => $tenant]);
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
        IntegrationConfig::factory()
            ->pantheon(['document_types' => ['material_issue' => 'RW', 'product_receipt' => 'PW', 'posted_status' => 'P']])
            ->create(['tenant_id' => $tenantId]);

        return app(PushStockDocuments::class)->run();
    }

    // ── PR #230 review fixes ────────────────────────────────────────────────

    public function test_a_soft_deleted_integration_row_does_not_drive_a_sync(): void
    {
        $tenant = Tenant::factory()->create()->id;
        IntegrationConfig::factory()->pantheon()->create(['tenant_id' => $tenant])->delete();

        // Only the tenant scope may be dropped when the command looks for work; a
        // deleted integration must stay invisible.
        $this->assertSame(
            0,
            IntegrationConfig::withoutGlobalScope(\App\Scopes\TenantScope::class)
                ->where('system_type', PantheonSettings::SYSTEM_TYPE)
                ->count(),
        );
    }

    public function test_an_uppercase_https_scheme_is_accepted(): void
    {
        // parse_url returns the scheme as written, so a lower-cased comparison is
        // what keeps HTTPS:// from being refused as insecure.
        $settings = $this->settings([
            'base_url' => 'HTTPS://paws.plant.local',
            'username' => 'openmes',
            'password' => 'secret',
            'company_db' => 'DEMO',
        ]);

        $this->assertNull($settings->transportProblem());
    }

    public function test_a_document_is_acknowledged_even_if_the_status_change_fails(): void
    {
        $tenant = Tenant::factory()->create()->id;
        $document = $this->postedDocumentFor($tenant, 'MI/2026/0010');

        Http::fake([
            '*/api/Users/authwithtoken' => Http::response(['token' => 'test-token']),
            '*/api/Move/insert' => Http::response(['acKey' => 'RW-9']),
            // Booked, but the status call breaks — the movement still exists in
            // Pantheon, so it must never be inserted a second time.
            '*/api/Move/changedocstatus/*' => Http::response(['error' => 'boom'], 500),
        ]);

        app(TenantContext::class)->set($tenant);
        $report = $this->pushFor($tenant);

        $this->assertSame('RW-9', $document->fresh()->erp_reference);
        $this->assertNotNull($document->fresh()->erp_synced_at, 'must not be re-booked on the next run');
        $this->assertSame(1, $report['imported']);
        // The failure is still reported, so nobody assumes the status was set.
        $this->assertStringContainsString('status could not be changed', $report['errors'][0]['message']);
    }

    public function test_a_line_without_an_item_code_fails_its_document_instead_of_sending_null(): void
    {
        $tenant = Tenant::factory()->create()->id;
        app(TenantContext::class)->set($tenant);

        $warehouse = Warehouse::factory()->rawMaterial()->create();
        $document = StockDocument::factory()->posted()->create([
            'document_no' => 'MI/2026/0011',
            'warehouse_id' => $warehouse->id,
        ]);
        // A line whose item is gone: nothing Pantheon could book against.
        $document->lines()->create(['quantity' => 5, 'unit_of_measure' => 'kg']);

        Http::fake([
            '*/api/Users/authwithtoken' => Http::response(['token' => 'test-token']),
            '*/api/Move/insert' => Http::response(['acKey' => 'RW-SHOULD-NOT-HAPPEN']),
        ]);

        $report = $this->pushFor($tenant);

        $this->assertSame(0, $report['imported']);
        $this->assertNull($document->fresh()->erp_synced_at);
        $this->assertStringContainsString('no material or product code', $report['errors'][0]['message']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/Move/insert'));
    }

    #[DataProvider('pantheonActiveFlags')]
    public function test_the_pantheon_active_flag_is_read_by_value_not_by_truthiness(mixed $value, ?bool $expected): void
    {
        $mapped = (new \ReflectionClass(SyncProducts::class))
            ->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod(SyncProducts::class, 'activeFlag');
        $result = $method->invoke($mapped, ['anActive' => $value]);

        // A plain (bool) cast made 'F' and 'N' active, silently resurrecting items
        // the customer had retired; an unknown value must leave the flag alone.
        $expected === null
            ? $this->assertSame([], $result)
            : $this->assertSame(['is_active' => $expected], $result);
    }

    public static function pantheonActiveFlags(): array
    {
        return [
            'numeric true' => [1, true],
            'numeric false' => [0, false],
            'T' => ['T', true],
            'F' => ['F', false],
            'N' => ['N', false],
            'false as text' => ['false', false],
            'real bool' => [true, true],
            'unknown value leaves it alone' => ['maybe', null],
        ];
    }
}
