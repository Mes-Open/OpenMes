<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OpenMES → ERP read-only master data: lines, warehouses and product types under
 * the `erp:masterdata:read` scope, cursor-paginated and tenant-scoped.
 */
class ErpMasterDataExportTest extends TestCase
{
    use RefreshDatabase;

    private const LISTS = ['/api/v1/erp/lines', '/api/v1/erp/product-types', '/api/v1/erp/warehouses'];

    private int $tenantId;

    private int $otherTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'Primary'])->id;
        $this->otherTenantId = Tenant::create(['name' => 'Other'])->id;
        $this->useTenant($this->tenantId);
    }

    /** The API-key middleware clears TenantContext after each request; re-set it before seeding more rows. */
    private function useTenant(int $tenantId): void
    {
        app(TenantContext::class)->set($tenantId);
    }

    private function keyWith(array $scopes, ?int $tenantId = null): string
    {
        [, $plaintext] = ApiKey::issue([
            'name' => 'ERP master-data key',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $tenantId ?? $this->tenantId,
        ]);

        return $plaintext;
    }

    private function readKey(): string
    {
        return $this->keyWith([ApiScope::MasterDataRead]);
    }

    /** @return list<string> */
    private function codes(string $url, string $key, string $field = 'code'): array
    {
        $response = $this->withHeader('X-Api-Key', $key)->getJson($url);
        $response->assertOk();

        return collect($response->json('data'))->pluck($field)->all();
    }

    public function test_lists_lines_with_codes_and_activity(): void
    {
        Line::factory()->create(['code' => 'L-ACTIVE', 'name' => 'Assembly 1', 'description' => 'Main line']);
        Line::factory()->inactive()->create(['code' => 'L-IDLE', 'name' => 'Old line']);

        $response = $this->withHeader('X-Api-Key', $this->readKey())->getJson('/api/v1/erp/lines');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['code', 'name', 'description', 'is_active', 'updated_at']],
                'meta' => ['next_cursor', 'has_more', 'count', 'per_page'],
            ]);

        $rows = collect($response->json('data'))->keyBy('code');
        $this->assertSame('Assembly 1', $rows['L-ACTIVE']['name']);
        $this->assertSame('Main line', $rows['L-ACTIVE']['description']);
        $this->assertTrue($rows['L-ACTIVE']['is_active']);
        $this->assertFalse($rows['L-IDLE']['is_active']);
        $this->assertArrayNotHasKey('id', $rows['L-ACTIVE']);
        $this->assertArrayNotHasKey('tenant_id', $rows['L-ACTIVE']);
    }

    public function test_lists_product_types_with_unit_and_template_flag(): void
    {
        $routed = ProductType::factory()->create(['code' => 'PT-ROUTED', 'unit_of_measure' => 'pcs']);
        ProcessTemplate::factory()->create(['product_type_id' => $routed->id, 'is_active' => true]);

        $unrouted = ProductType::factory()->create(['code' => 'PT-DRAFT', 'unit_of_measure' => 'kg']);
        ProcessTemplate::factory()->inactive()->create(['product_type_id' => $unrouted->id]);

        ProductType::factory()->create(['code' => 'PT-BARE']);

        $response = $this->withHeader('X-Api-Key', $this->readKey())->getJson('/api/v1/erp/product-types');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['code', 'name', 'unit_of_measure', 'is_active', 'has_active_process_template', 'updated_at']]]);

        $rows = collect($response->json('data'))->keyBy('code');
        $this->assertTrue($rows['PT-ROUTED']['has_active_process_template']);
        $this->assertSame('pcs', $rows['PT-ROUTED']['unit_of_measure']);
        $this->assertFalse($rows['PT-DRAFT']['has_active_process_template']);
        $this->assertSame('kg', $rows['PT-DRAFT']['unit_of_measure']);
        $this->assertFalse($rows['PT-BARE']['has_active_process_template']);
    }

    public function test_lists_warehouses_with_erp_code(): void
    {
        Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1', 'erp_code' => 'MAG01', 'name' => 'Raw store']);

        $response = $this->withHeader('X-Api-Key', $this->readKey())->getJson('/api/v1/erp/warehouses');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['code', 'erp_code', 'name', 'kind', 'is_default', 'is_active', 'updated_at']]]);

        $row = collect($response->json('data'))->firstWhere('code', 'RAW-1');
        $this->assertSame('MAG01', $row['erp_code']);
        $this->assertSame('Raw store', $row['name']);
        $this->assertSame(Warehouse::KIND_RAW_MATERIAL, $row['kind']);
        $this->assertTrue($row['is_active']);
    }

    public function test_lists_require_the_masterdata_read_scope(): void
    {
        $key = $this->keyWith([ApiScope::MasterDataWrite, ApiScope::ProductionRead, ApiScope::StockRead]);

        foreach (self::LISTS as $url) {
            $this->withHeader('X-Api-Key', $key)->getJson($url)->assertStatus(403);
        }
    }

    public function test_lists_require_a_valid_key(): void
    {
        [, $inactive] = ApiKey::issue([
            'name' => 'disabled',
            'scopes' => [ApiScope::MasterDataRead->value],
            'tenant_id' => $this->tenantId,
            'is_active' => false,
        ]);

        foreach (self::LISTS as $url) {
            $this->getJson($url)->assertStatus(401);
            $this->withHeader('X-Api-Key', $inactive)->getJson($url)->assertStatus(401);
            $this->flushHeaders();
        }
    }

    public function test_lists_are_isolated_to_the_key_tenant(): void
    {
        Line::factory()->create(['code' => 'MINE-L']);
        ProductType::factory()->create(['code' => 'MINE-PT']);
        Warehouse::factory()->create(['code' => 'MINE-WH']);

        $this->useTenant($this->otherTenantId);
        Line::factory()->create(['code' => 'OTHER-L', 'tenant_id' => $this->otherTenantId]);
        ProductType::factory()->create(['code' => 'OTHER-PT', 'tenant_id' => $this->otherTenantId]);
        Warehouse::factory()->create(['code' => 'OTHER-WH', 'tenant_id' => $this->otherTenantId]);
        $this->useTenant($this->tenantId);

        $key = $this->readKey();

        $lines = $this->codes('/api/v1/erp/lines', $key);
        $this->assertContains('MINE-L', $lines);
        $this->assertNotContains('OTHER-L', $lines);

        $types = $this->codes('/api/v1/erp/product-types', $key);
        $this->assertContains('MINE-PT', $types);
        $this->assertNotContains('OTHER-PT', $types);

        $warehouses = $this->codes('/api/v1/erp/warehouses', $key);
        $this->assertContains('MINE-WH', $warehouses);
        $this->assertNotContains('OTHER-WH', $warehouses);

        // And the other tenant's key sees only its own rows.
        $otherLines = $this->codes('/api/v1/erp/lines', $this->keyWith([ApiScope::MasterDataRead], $this->otherTenantId));
        $this->assertContains('OTHER-L', $otherLines);
        $this->assertNotContains('MINE-L', $otherLines);
    }

    public function test_cursor_pagination_walks_every_row(): void
    {
        Line::factory()->count(3)->create();
        $total = Line::count();
        $key = $this->readKey();

        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $url = '/api/v1/erp/lines?limit=2'.($cursor ? '&cursor='.urlencode($cursor) : '');
            $response = $this->withHeader('X-Api-Key', $key)->getJson($url)->assertOk();

            $meta = $response->json('meta');
            $this->assertSame(2, $meta['per_page']);
            $this->assertLessThanOrEqual(2, $meta['count']);
            $this->assertSame($meta['has_more'], $meta['next_cursor'] !== null);

            $seen = array_merge($seen, collect($response->json('data'))->pluck('code')->all());
            $cursor = $meta['next_cursor'];
            $pages++;
        } while ($cursor !== null && $pages < 10);

        $this->assertNull($cursor);
        $this->assertSame((int) ceil($total / 2), $pages);
        $this->assertCount($total, $seen);
        $this->assertSame($seen, array_values(array_unique($seen)));
    }

    public function test_since_filters_by_updated_at(): void
    {
        $old = Line::factory()->create(['code' => 'L-OLD']);
        $old->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();
        Line::factory()->create(['code' => 'L-NEW']);

        $oldType = ProductType::factory()->create(['code' => 'PT-OLD']);
        $oldType->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();
        ProductType::factory()->create(['code' => 'PT-NEW']);

        $since = urlencode(now()->subDay()->toIso8601String());
        $key = $this->readKey();

        $lines = $this->codes("/api/v1/erp/lines?since={$since}", $key);
        $this->assertContains('L-NEW', $lines);
        $this->assertNotContains('L-OLD', $lines);

        $types = $this->codes("/api/v1/erp/product-types?since={$since}", $key);
        $this->assertContains('PT-NEW', $types);
        $this->assertNotContains('PT-OLD', $types);
    }

    public function test_active_filter_narrows_to_active_or_inactive_rows(): void
    {
        Line::factory()->create(['code' => 'L-ON']);
        Line::factory()->inactive()->create(['code' => 'L-OFF']);
        $key = $this->readKey();

        $active = $this->codes('/api/v1/erp/lines?active=1', $key);
        $this->assertContains('L-ON', $active);
        $this->assertNotContains('L-OFF', $active);

        $inactive = $this->codes('/api/v1/erp/lines?active=0', $key);
        $this->assertContains('L-OFF', $inactive);
        $this->assertNotContains('L-ON', $inactive);

        $all = $this->codes('/api/v1/erp/lines', $key);
        $this->assertContains('L-ON', $all);
        $this->assertContains('L-OFF', $all);
    }

    public function test_soft_deleted_rows_are_excluded(): void
    {
        Line::factory()->create(['code' => 'L-GONE'])->delete();
        ProductType::factory()->create(['code' => 'PT-GONE'])->delete();
        Warehouse::factory()->create(['code' => 'WH-GONE'])->delete();
        $key = $this->readKey();

        $this->assertNotContains('L-GONE', $this->codes('/api/v1/erp/lines', $key));
        $this->assertNotContains('PT-GONE', $this->codes('/api/v1/erp/product-types', $key));
        $this->assertNotContains('WH-GONE', $this->codes('/api/v1/erp/warehouses', $key));
    }

    public function test_warehouses_404_when_the_module_is_disabled(): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['warehouse'])));

        // 404 regardless of scope: a disabled module looks absent, not forbidden.
        $this->withHeader('X-Api-Key', $this->readKey())->getJson('/api/v1/erp/warehouses')->assertStatus(404);
        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))->getJson('/api/v1/erp/warehouses')->assertStatus(404);

        // Lines and product types are core data and stay available.
        $this->withHeader('X-Api-Key', $this->readKey())->getJson('/api/v1/erp/lines')->assertOk();
        $this->withHeader('X-Api-Key', $this->readKey())->getJson('/api/v1/erp/product-types')->assertOk();
    }

    public function test_list_filters_are_validated(): void
    {
        $key = $this->readKey();

        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/erp/lines?limit=0')
            ->assertStatus(422)->assertJsonValidationErrors('limit');
        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/erp/lines?limit=501')
            ->assertStatus(422)->assertJsonValidationErrors('limit');
        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/erp/product-types?since=not-a-date')
            ->assertStatus(422)->assertJsonValidationErrors('since');
        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/erp/warehouses?active=maybe')
            ->assertStatus(422)->assertJsonValidationErrors('active');
    }
}
