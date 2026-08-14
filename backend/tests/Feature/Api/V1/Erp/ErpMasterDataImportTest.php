<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\BomItem;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ERP master-data import endpoints (#212): products (with the classification
 * filter), materials, material lots with available quantities, and recipes.
 */
class ErpMasterDataImportTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'ERP Tenant'])->id;
        app(TenantContext::class)->set($this->tenantId);
    }

    private function keyWith(array $scopes): string
    {
        [, $plaintext] = ApiKey::issue([
            'name' => 'ERP master data key',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $this->tenantId,
        ]);

        return $plaintext;
    }

    private function importAs(string $uri, array $payload, ?string $key = null)
    {
        return $this->withHeader('X-Api-Key', $key ?? $this->keyWith([ApiScope::MasterDataWrite]))
            ->postJson($uri, $payload);
    }

    // ── Products ─────────────────────────────────────────────────────────────

    public function test_products_import_creates_and_updates_by_code(): void
    {
        $response = $this->importAs('/api/v1/erp/products/import', [
            'external_system' => 'pantheon',
            'products' => [
                ['code' => 'BREAD-01', 'name' => 'Rye bread', 'category' => 'FINISHED', 'unit_of_measure' => 'pcs'],
                ['code' => 'BREAD-02', 'name' => 'Wheat bread', 'category' => 'FINISHED'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.errors', []);

        $this->assertDatabaseHas('product_types', [
            'code' => 'BREAD-01',
            'name' => 'Rye bread',
            'category' => 'FINISHED',
            'external_code' => 'BREAD-01',
            'external_system' => 'pantheon',
        ]);

        // Second run with a changed name updates instead of duplicating.
        $this->importAs('/api/v1/erp/products/import', [
            'products' => [['code' => 'BREAD-01', 'name' => 'Rye bread 500g', 'category' => 'FINISHED']],
        ])->assertOk()->assertJsonPath('data.updated', 1);

        $this->assertSame(1, ProductType::where('code', 'BREAD-01')->count());
        $this->assertSame('Rye bread 500g', ProductType::where('code', 'BREAD-01')->value('name'));
    }

    public function test_products_import_skips_categories_outside_the_allowlist(): void
    {
        $response = $this->importAs('/api/v1/erp/products/import', [
            // An ERP item dump holds products and raw materials in one table;
            // only the listed classifications are products.
            'only_categories' => ['FINISHED'],
            'products' => [
                ['code' => 'BREAD-01', 'name' => 'Rye bread', 'category' => 'FINISHED'],
                ['code' => 'FLOUR-01', 'name' => 'Rye flour', 'category' => 'RAW'],
                ['code' => 'NOCAT-01', 'name' => 'Unclassified'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.skipped', 2);

        $this->assertDatabaseHas('product_types', ['code' => 'BREAD-01']);
        $this->assertDatabaseMissing('product_types', ['code' => 'FLOUR-01']);
        $this->assertDatabaseMissing('product_types', ['code' => 'NOCAT-01']);
    }

    public function test_products_category_filter_ignores_case(): void
    {
        $this->importAs('/api/v1/erp/products/import', [
            'only_categories' => ['finished'],
            'products' => [['code' => 'BREAD-01', 'name' => 'Rye bread', 'category' => 'Finished']],
        ])->assertOk()->assertJsonPath('data.imported', 1);

        $this->assertDatabaseHas('product_types', ['code' => 'BREAD-01']);
    }

    public function test_products_import_validates_payload(): void
    {
        $this->importAs('/api/v1/erp/products/import', ['products' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('products');

        $this->importAs('/api/v1/erp/products/import', ['products' => [['name' => 'No code']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('products.0.code');
    }

    public function test_master_data_import_requires_its_own_scope(): void
    {
        $wrongScope = $this->keyWith([ApiScope::OrdersImport]);

        $this->importAs('/api/v1/erp/products/import', [
            'products' => [['code' => 'BREAD-01']],
        ], $wrongScope)->assertForbidden();

        // And no key at all is unauthenticated, not merely unauthorized.
        // flushHeaders: withHeader() persists across requests within a test, so
        // the wrong-scope key above would otherwise still be sent.
        $this->flushHeaders();

        $this->postJson('/api/v1/erp/products/import', [
            'products' => [['code' => 'BREAD-01']],
        ])->assertUnauthorized();
    }

    // ── Materials ────────────────────────────────────────────────────────────

    public function test_materials_import_resolves_type_from_category(): void
    {
        $response = $this->importAs('/api/v1/erp/materials/import', [
            'materials' => [
                ['code' => 'FLOUR-01', 'name' => 'Rye flour', 'category' => 'RAW', 'unit_of_measure' => 'kg', 'tracking_type' => 'batch'],
            ],
        ]);

        $response->assertOk()->assertJsonPath('data.imported', 1);

        $material = Material::where('code', 'FLOUR-01')->first();

        $this->assertSame('kg', $material->unit_of_measure);
        $this->assertSame('batch', $material->tracking_type);
        // The ERP classification becomes the OpenMES material type.
        $this->assertSame('RAW', MaterialType::find($material->material_type_id)->code);
    }

    public function test_materials_import_reports_bad_tracking_type_per_row(): void
    {
        $response = $this->importAs('/api/v1/erp/materials/import', [
            'materials' => [
                ['code' => 'FLOUR-01', 'name' => 'Rye flour'],
                ['code' => 'YEAST-01', 'name' => 'Yeast', 'tracking_type' => 'nonsense'],
            ],
        ]);

        // A bad enum in one row is a 422 from the form request — it is a payload
        // shape problem, not a resolution problem.
        $response->assertStatus(422)->assertJsonValidationErrors('materials.1.tracking_type');
    }

    // ── Material lots ────────────────────────────────────────────────────────

    public function test_material_lots_import_writes_quantities_and_warehouse_balance(): void
    {
        Material::factory()->create(['code' => 'FLOUR-01', 'unit_of_measure' => 'kg']);
        $warehouse = Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);

        $response = $this->importAs('/api/v1/erp/material-lots/import', [
            'warehouse_code' => 'RAW-1',
            'lots' => [
                ['material_code' => 'FLOUR-01', 'lot_number' => 'L-1', 'quantity_available' => 120.5],
                ['material_code' => 'FLOUR-01', 'lot_number' => 'L-2', 'quantity_available' => 80],
            ],
        ]);

        $response->assertOk()->assertJsonPath('data.imported', 2);

        $lot = MaterialLot::where('lot_number', 'L-1')->first();
        $this->assertSame($warehouse->id, $lot->warehouse_id);
        $this->assertEquals(120.5, (float) $lot->quantity_available);
        // An ERP-reported free quantity is stock the ERP already cleared for use.
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->status);

        // The per-warehouse total is the sum of the lots.
        $total = WarehouseStock::where('warehouse_id', $warehouse->id)
            ->whereNull('material_lot_id')
            ->value('quantity');

        $this->assertEquals(200.5, (float) $total);
    }

    public function test_material_lots_import_reconciles_the_global_material_quantity(): void
    {
        // Two views of the same stock — the per-warehouse balance and the global
        // materials.stock_quantity — must not drift apart, or an issue posted
        // later drives the global figure negative while the warehouse shows stock.
        $material = Material::factory()->create(['code' => 'FLOUR-01', 'stock_quantity' => 0]);
        Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);

        $this->importAs('/api/v1/erp/material-lots/import', [
            'warehouse_code' => 'RAW-1',
            'lots' => [
                ['material_code' => 'FLOUR-01', 'lot_number' => 'L-1', 'quantity_available' => 120.5],
                ['material_code' => 'FLOUR-01', 'lot_number' => 'L-2', 'quantity_available' => 80],
            ],
        ])->assertOk();

        $this->assertEquals(200.5, (float) $material->fresh()->stock_quantity);
        // The correction is audited, not written silently.
        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $material->id,
            'movement_type' => \App\Models\StockMovement::TYPE_ADJUSTMENT,
            'source_type' => \App\Models\StockMovement::SOURCE_ERP_SYNC,
        ]);
    }

    public function test_material_lots_import_replaces_quantity_so_resync_converges(): void
    {
        Material::factory()->create(['code' => 'FLOUR-01']);
        Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);

        $payload = [
            'warehouse_code' => 'RAW-1',
            'lots' => [['material_code' => 'FLOUR-01', 'lot_number' => 'L-1', 'quantity_available' => 100]],
        ];

        $this->importAs('/api/v1/erp/material-lots/import', $payload)->assertOk();

        // Same lot, less left in the ERP: the balance follows down, not up.
        $payload['lots'][0]['quantity_available'] = 60;
        $this->importAs('/api/v1/erp/material-lots/import', $payload)
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertEquals(60, (float) MaterialLot::where('lot_number', 'L-1')->value('quantity_available'));
        $this->assertSame(1, MaterialLot::where('lot_number', 'L-1')->count());
    }

    public function test_material_lots_import_reports_unknown_references_per_row(): void
    {
        Material::factory()->create(['code' => 'FLOUR-01']);
        Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);

        $response = $this->importAs('/api/v1/erp/material-lots/import', [
            'lots' => [
                ['material_code' => 'FLOUR-01', 'lot_number' => 'L-1', 'quantity_available' => 10, 'warehouse_code' => 'RAW-1'],
                ['material_code' => 'GHOST', 'lot_number' => 'L-2', 'quantity_available' => 10],
                ['material_code' => 'FLOUR-01', 'lot_number' => 'L-3', 'quantity_available' => 10, 'warehouse_code' => 'NOPE'],
            ],
        ]);

        // The good row still landed — one bad reference must not fail a batch.
        $response->assertStatus(207)
            ->assertJsonPath('data.imported', 1)
            ->assertJsonCount(2, 'data.errors')
            ->assertJsonPath('data.errors.0.field', 'material_code')
            ->assertJsonPath('data.errors.1.field', 'warehouse_code');
    }

    // ── Recipes (BOM) ────────────────────────────────────────────────────────

    public function test_boms_import_creates_components_per_unit(): void
    {
        $product = ProductType::factory()->create(['code' => 'BREAD-01']);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        Material::factory()->create(['code' => 'FLOUR-01']);
        Material::factory()->create(['code' => 'YEAST-01']);

        $response = $this->importAs('/api/v1/erp/boms/import', [
            'recipes' => [[
                'product_type_code' => 'BREAD-01',
                'components' => [
                    ['material_code' => 'FLOUR-01', 'quantity_per_unit' => 0.5],
                    ['material_code' => 'YEAST-01', 'quantity_per_unit' => 0.01, 'scrap_percentage' => 2],
                ],
            ]],
        ]);

        $response->assertOk()->assertJsonPath('data.imported', 1);

        $this->assertSame(2, BomItem::where('process_template_id', $template->id)->count());
        $this->assertEquals(0.5, (float) BomItem::where('process_template_id', $template->id)
            ->whereHas('material', fn ($q) => $q->where('code', 'FLOUR-01'))
            ->value('quantity_per_unit'));
    }

    public function test_boms_import_replace_mode_drops_components_the_erp_no_longer_lists(): void
    {
        $product = ProductType::factory()->create(['code' => 'BREAD-01']);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        Material::factory()->create(['code' => 'FLOUR-01']);
        $dropped = Material::factory()->create(['code' => 'OLD-01']);

        BomItem::create([
            'process_template_id' => $template->id,
            'material_id' => $dropped->id,
            'quantity_per_unit' => 1,
        ]);

        $this->importAs('/api/v1/erp/boms/import', [
            'recipes' => [[
                'product_type_code' => 'BREAD-01',
                'components' => [['material_code' => 'FLOUR-01', 'quantity_per_unit' => 0.5]],
            ]],
        ])->assertOk()->assertJsonPath('data.updated', 1);

        $this->assertSame(1, BomItem::where('process_template_id', $template->id)->count());
        $this->assertSoftDeleted('bom_items', ['material_id' => $dropped->id]);
    }

    public function test_boms_import_merge_mode_keeps_existing_components(): void
    {
        $product = ProductType::factory()->create(['code' => 'BREAD-01']);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        Material::factory()->create(['code' => 'FLOUR-01']);
        $kept = Material::factory()->create(['code' => 'SALT-01']);

        BomItem::create([
            'process_template_id' => $template->id,
            'material_id' => $kept->id,
            'quantity_per_unit' => 1,
        ]);

        $this->importAs('/api/v1/erp/boms/import', [
            'mode' => 'merge',
            'recipes' => [[
                'product_type_code' => 'BREAD-01',
                'components' => [['material_code' => 'FLOUR-01', 'quantity_per_unit' => 0.5]],
            ]],
        ])->assertOk();

        $this->assertSame(2, BomItem::where('process_template_id', $template->id)->count());
    }

    public function test_boms_import_reports_a_recipe_with_no_template_or_unknown_material(): void
    {
        // Product exists but has no process template to attach a recipe to.
        ProductType::factory()->create(['code' => 'BREAD-01']);
        $product = ProductType::factory()->create(['code' => 'BREAD-02']);
        ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        Material::factory()->create(['code' => 'FLOUR-01']);

        $response = $this->importAs('/api/v1/erp/boms/import', [
            'recipes' => [
                ['product_type_code' => 'BREAD-01', 'components' => [['material_code' => 'FLOUR-01', 'quantity_per_unit' => 1]]],
                ['product_type_code' => 'BREAD-02', 'components' => [['material_code' => 'GHOST', 'quantity_per_unit' => 1]]],
                ['product_type_code' => 'GHOST-01', 'components' => [['material_code' => 'FLOUR-01', 'quantity_per_unit' => 1]]],
            ],
        ]);

        $response->assertStatus(207)
            ->assertJsonCount(3, 'data.errors')
            ->assertJsonPath('data.errors.0.field', 'process_template_version')
            ->assertJsonPath('data.errors.1.field', 'components')
            ->assertJsonPath('data.errors.2.field', 'product_type_code');

        // The failing recipe wrote nothing — a partial BOM is worse than none.
        $this->assertSame(0, BomItem::count());
    }

    public function test_master_data_import_is_scoped_to_the_keys_tenant(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other'])->id;

        [, $otherKey] = ApiKey::issue([
            'name' => 'Other tenant key',
            'scopes' => [ApiScope::MasterDataWrite->value],
            'tenant_id' => $otherTenant,
        ]);

        $this->importAs('/api/v1/erp/products/import', [
            'products' => [['code' => 'BREAD-01', 'name' => 'Rye bread']],
        ], $otherKey)->assertOk();

        $this->assertDatabaseHas('product_types', ['code' => 'BREAD-01', 'tenant_id' => $otherTenant]);
        $this->assertDatabaseMissing('product_types', ['code' => 'BREAD-01', 'tenant_id' => $this->tenantId]);
    }
}
