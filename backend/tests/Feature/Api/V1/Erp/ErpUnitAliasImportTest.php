<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\UnitAlias;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Units of measure in ERP imports: the names an ERP sends are translated
 * through the unit aliases, and a quantity in a unit that is not the item's
 * fails its row instead of being booked as if it were — 340 g must never
 * become 340 kg.
 */
class ErpUnitAliasImportTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'ERP Tenant'])->id;
        app(TenantContext::class)->set($this->tenantId);
    }

    private function keyWith(array $scopes, ?int $tenantId = null): string
    {
        [, $plaintext] = ApiKey::issue([
            'name' => 'ERP unit key',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $tenantId ?? $this->tenantId,
        ]);

        return $plaintext;
    }

    private function importAs(string $uri, array $payload, ApiScope $scope = ApiScope::MasterDataWrite, ?int $tenantId = null)
    {
        return $this->withHeader('X-Api-Key', $this->keyWith([$scope], $tenantId))->postJson($uri, $payload);
    }

    // ── Item masters: normalize, warn on unknown ─────────────────────────────

    public function test_products_import_translates_an_erp_unit_name(): void
    {
        $this->importAs('/api/v1/erp/products/import', [
            'products' => [['code' => 'BREAD-01', 'name' => 'Rye bread', 'unit_of_measure' => 'Units']],
        ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.warnings', []);

        $this->assertSame('pcs', ProductType::where('code', 'BREAD-01')->value('unit_of_measure'));
    }

    public function test_materials_import_translates_an_iso_unit_code(): void
    {
        $this->importAs('/api/v1/erp/materials/import', [
            'materials' => [['code' => 'FLOUR-01', 'name' => 'Rye flour', 'unit_of_measure' => 'KGM']],
        ])->assertOk()->assertJsonPath('data.warnings', []);

        $this->assertSame('kg', Material::where('code', 'FLOUR-01')->value('unit_of_measure'));
    }

    public function test_an_unknown_unit_is_saved_as_sent_with_a_warning(): void
    {
        $this->importAs('/api/v1/erp/products/import', [
            'products' => [
                ['code' => 'BEER-01', 'name' => 'Beer', 'unit_of_measure' => ' Fässer '],
                ['code' => 'BREAD-01', 'name' => 'Bread', 'unit_of_measure' => 'szt.'],
            ],
        ])
            // A warning never turns a successful import into a partial one.
            ->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.errors', [])
            ->assertJsonCount(1, 'data.warnings')
            ->assertJsonPath('data.warnings.0.row', 1)
            ->assertJsonPath('data.warnings.0.field', 'unit_of_measure');

        $this->assertSame('Fässer', ProductType::where('code', 'BEER-01')->value('unit_of_measure'));
        $this->assertSame('pcs', ProductType::where('code', 'BREAD-01')->value('unit_of_measure'));
    }

    public function test_a_row_without_a_unit_keeps_todays_defaults(): void
    {
        $this->importAs('/api/v1/erp/materials/import', [
            'materials' => [['code' => 'FLOUR-01', 'name' => 'Rye flour']],
        ])->assertOk()->assertJsonPath('data.warnings', []);

        $this->assertSame('pcs', Material::where('code', 'FLOUR-01')->value('unit_of_measure'));
    }

    public function test_every_importer_reports_warnings(): void
    {
        $product = ProductType::factory()->create(['code' => 'BREAD-01']);
        Material::factory()->create(['code' => 'FLOUR-01']);
        Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);

        $this->importAs('/api/v1/erp/boms/import', [
            'recipes' => [['product_type_code' => $product->code, 'components' => [['material_code' => 'FLOUR-01', 'quantity_per_unit' => 1]]]],
        ])->assertJsonPath('data.warnings', []);

        $this->importAs('/api/v1/erp/material-lots/import', [
            'lots' => [['material_code' => 'FLOUR-01', 'lot_number' => 'L-1', 'quantity_available' => 1]],
        ])->assertOk()->assertJsonPath('data.warnings', []);

        $this->importAs('/api/v1/erp/stock/import', [
            'warehouse_code' => 'RAW-1',
            'balances' => [['material_code' => 'FLOUR-01', 'quantity' => 1]],
        ], ApiScope::StockWrite)->assertOk()->assertJsonPath('data.warnings', []);
    }

    // ── Stock: a mismatching unit fails its row ──────────────────────────────

    public function test_stock_rows_in_the_wrong_unit_fail_while_the_rest_apply(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);
        $flour = Material::factory()->create(['code' => 'FLOUR-01', 'unit_of_measure' => 'kg', 'stock_quantity' => 0]);
        $sugar = Material::factory()->create(['code' => 'SUGAR-01', 'unit_of_measure' => 'kg', 'stock_quantity' => 0]);
        $salt = Material::factory()->create(['code' => 'SALT-01', 'unit_of_measure' => 'kg', 'stock_quantity' => 0]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $sugar->id,
            'quantity' => 12,
            'unit_of_measure' => 'kg',
        ]);

        $response = $this->importAs('/api/v1/erp/stock/import', [
            'warehouse_code' => 'RAW-1',
            'balances' => [
                ['material_code' => 'FLOUR-01', 'quantity' => 100, 'unit_of_measure' => 'KGM'],
                ['material_code' => 'SUGAR-01', 'quantity' => 340, 'unit_of_measure' => 'g'],
                ['material_code' => 'SALT-01', 'quantity' => 5],
            ],
        ], ApiScope::StockWrite);

        $response->assertStatus(207)
            ->assertJsonPath('data.imported', 2)
            ->assertJsonCount(1, 'data.errors')
            ->assertJsonPath('data.errors.0.row', 2)
            ->assertJsonPath('data.errors.0.field', 'unit_of_measure');
        $this->assertStringContainsString('kg', $response->json('data.errors.0.message'));
        $this->assertStringContainsString('SUGAR-01', $response->json('data.errors.0.message'));

        $balance = fn (Material $m) => WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('material_id', $m->id)->whereNull('material_lot_id')->first();

        // The balance stored in the item's own unit, not the ERP's spelling.
        $this->assertEquals(100, (float) $balance($flour)->quantity);
        $this->assertSame('kg', $balance($flour)->unit_of_measure);
        // The rejected row left the old balance alone.
        $this->assertEquals(12, (float) $balance($sugar)->quantity);
        // No unit on the row → as before: the item's unit.
        $this->assertEquals(5, (float) $balance($salt)->quantity);
        $this->assertSame('kg', $balance($salt)->unit_of_measure);

        $this->assertEquals(100, (float) $flour->fresh()->stock_quantity);
        $this->assertEquals(5, (float) $salt->fresh()->stock_quantity);
    }

    public function test_a_product_balance_in_an_alias_of_its_unit_is_accepted(): void
    {
        $warehouse = Warehouse::factory()->finishedGoods()->create(['code' => 'FG-1']);
        $product = ProductType::factory()->create(['code' => 'BREAD-01', 'unit_of_measure' => 'pcs']);

        $this->importAs('/api/v1/erp/stock/import', [
            'warehouse_code' => 'FG-1',
            'balances' => [['product_type_code' => 'BREAD-01', 'quantity' => 40, 'unit_of_measure' => 'szt.']],
        ], ApiScope::StockWrite)->assertOk();

        $this->assertSame('pcs', WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('product_type_id', $product->id)->value('unit_of_measure'));
    }

    public function test_an_unknown_unit_that_differs_from_the_items_fails_the_row(): void
    {
        Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);
        Material::factory()->create(['code' => 'FLOUR-01', 'unit_of_measure' => 'kg']);

        $this->importAs('/api/v1/erp/stock/import', [
            'warehouse_code' => 'RAW-1',
            'balances' => [['material_code' => 'FLOUR-01', 'quantity' => 3, 'unit_of_measure' => 'sack']],
        ], ApiScope::StockWrite)
            ->assertStatus(207)
            ->assertJsonPath('data.errors.0.field', 'unit_of_measure');

        $this->assertDatabaseCount('warehouse_stocks', 0);
    }

    public function test_a_legacy_item_unit_still_matches_its_canonical_name(): void
    {
        // Items imported before aliases existed may carry "szt" — a row in
        // "pcs" means the same and must not be refused.
        Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);
        Material::factory()->create(['code' => 'BOLT-01', 'unit_of_measure' => 'szt']);

        $this->importAs('/api/v1/erp/stock/import', [
            'warehouse_code' => 'RAW-1',
            'balances' => [['material_code' => 'BOLT-01', 'quantity' => 500, 'unit_of_measure' => 'pcs']],
        ], ApiScope::StockWrite)->assertOk();
    }

    // ── Material lots ────────────────────────────────────────────────────────

    public function test_a_lot_in_an_alias_of_the_material_unit_is_stored_in_that_unit(): void
    {
        Material::factory()->create(['code' => 'FLOUR-01', 'unit_of_measure' => 'kg']);

        $this->importAs('/api/v1/erp/material-lots/import', [
            'lots' => [['material_code' => 'FLOUR-01', 'lot_number' => 'L-1', 'quantity_available' => 25, 'unit_of_measure' => 'KGM']],
        ])->assertOk();

        $this->assertSame('kg', MaterialLot::where('lot_number', 'L-1')->value('unit_of_measure'));
    }

    public function test_a_lot_in_the_wrong_unit_is_not_created(): void
    {
        Material::factory()->create(['code' => 'FLOUR-01', 'unit_of_measure' => 'kg']);
        Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);

        $this->importAs('/api/v1/erp/material-lots/import', [
            'warehouse_code' => 'RAW-1',
            'lots' => [
                ['material_code' => 'FLOUR-01', 'lot_number' => 'L-1', 'quantity_available' => 25000, 'unit_of_measure' => 'g'],
                ['material_code' => 'FLOUR-01', 'lot_number' => 'L-2', 'quantity_available' => 10],
            ],
        ])
            ->assertStatus(207)
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.errors.0.row', 1)
            ->assertJsonPath('data.errors.0.field', 'unit_of_measure');

        $this->assertDatabaseMissing('material_lots', ['lot_number' => 'L-1']);
        $this->assertEquals(10, (float) WarehouseStock::whereNull('material_lot_id')->value('quantity'));
    }

    // ── Tenants ──────────────────────────────────────────────────────────────

    public function test_another_tenants_alias_is_never_used(): void
    {
        $other = Tenant::create(['name' => 'Other'])->id;
        UnitAlias::create(['alias' => 'Fass', 'unit' => 'l', 'tenant_id' => $other]);

        $this->importAs('/api/v1/erp/products/import', [
            'products' => [['code' => 'BEER-01', 'name' => 'Beer', 'unit_of_measure' => 'Fass']],
        ])->assertOk()->assertJsonCount(1, 'data.warnings');

        $this->assertSame('Fass', ProductType::where('code', 'BEER-01')->value('unit_of_measure'));
    }

    public function test_a_tenants_own_alias_overrides_the_default(): void
    {
        // The defaults map "st" to pcs; this tenant's ERP means metres, so its
        // admin repoints the tenant's own copy.
        UnitAlias::where('alias_normalized', 'st')->firstOrFail()->update(['unit' => 'm']);
        $other = Tenant::create(['name' => 'Other'])->id;

        $this->importAs('/api/v1/erp/materials/import', [
            'materials' => [['code' => 'CABLE-01', 'name' => 'Cable', 'unit_of_measure' => 'st']],
        ])->assertOk();

        $this->assertSame('m', Material::where('code', 'CABLE-01')->value('unit_of_measure'));

        // The other tenant still gets the default.
        app(TenantContext::class)->set($other);
        $this->importAs('/api/v1/erp/materials/import', [
            'materials' => [['code' => 'BOLT-01', 'name' => 'Bolt', 'unit_of_measure' => 'st']],
        ], tenantId: $other)->assertOk();

        $this->assertSame('pcs', Material::withoutGlobalScopes()->where('code', 'BOLT-01')->value('unit_of_measure'));
    }

    public function test_a_deleted_alias_stops_resolving(): void
    {
        // The tenant's copy of "st → pcs" is wrong for this ERP, so the admin
        // deletes it. It must not fall back to the install-wide default.
        UnitAlias::where('alias_normalized', 'st')->firstOrFail()->delete();

        $this->importAs('/api/v1/erp/products/import', [
            'products' => [['code' => 'CABLE-01', 'name' => 'Cable', 'unit_of_measure' => 'st']],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.warnings')
            ->assertJsonPath('data.warnings.0.field', 'unit_of_measure');

        $this->assertSame('st', ProductType::where('code', 'CABLE-01')->value('unit_of_measure'));
    }
}
