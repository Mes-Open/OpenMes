<?php

namespace Tests\Feature\Import;

use App\Import\Importers\BomImporter;
use App\Import\Importers\MaterialImporter;
use App\Import\Importers\ProductTypeImporter;
use App\Import\Importers\WorkOrderImporter;
use App\Import\ImportRegistry;
use App\Models\BomItem;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The entity adapters behind the unified importer: each turns canonical rows
 * into writes and reports per row in the ReportsImportRows shape.
 */
class EntityImportersTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_lists_the_four_entities_and_restricts_supervisors(): void
    {
        $registry = app(ImportRegistry::class);

        $this->assertSame(['product_types', 'materials', 'work_orders', 'boms'], $registry->keys());
        $this->assertSame(['product-types', 'materials', 'work-orders', 'boms'], ImportRegistry::slugs());
        $this->assertSame(['work_orders'], array_keys($registry->forSection('supervisor')));
        $this->assertInstanceOf(MaterialImporter::class, $registry->fromSlug('materials'));
        $this->assertNull($registry->fromSlug('nope'));
    }

    public function test_missing_identifiers_cover_required_fields_and_any_of_groups(): void
    {
        $materials = app(MaterialImporter::class);

        $this->assertSame(['name', 'code | external_code'], $materials->missingIdentifiers(['x' => '_ignore']));
        $this->assertSame([], $materials->missingIdentifiers(['a' => 'external_code', 'b' => 'name']));
        $this->assertSame([], $materials->missingIdentifiers(['a' => 'code', 'b' => 'name']));

        $orders = app(WorkOrderImporter::class);
        $this->assertSame(['quantity'], $orders->missingIdentifiers(['a' => 'order_no', 'b' => 'custom:x']));
    }

    public function test_product_types_created_updated_skipped_and_errored(): void
    {
        ProductType::factory()->create(['code' => 'OLD', 'name' => 'Old name']);

        $result = app(ProductTypeImporter::class)->import([
            ['code' => 'NEW', 'name' => 'New', 'category' => 'FG'],
            ['code' => 'OLD', 'name' => 'Renamed', 'category' => 'FG'],
            ['code' => 'RAW', 'name' => 'Raw', 'category' => 'RM'],
            ['name' => 'no code'],
        ], ['strategy' => 'update_or_create', 'only_categories' => 'fg', 'external_system' => 'erp']);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(4, $result['errors'][0]['row']);
        $this->assertSame('code', $result['errors'][0]['field']);
        $this->assertDatabaseHas('product_types', ['code' => 'OLD', 'name' => 'Renamed']);
        $this->assertDatabaseHas('product_types', ['code' => 'NEW', 'external_system' => 'erp']);

        $skip = app(ProductTypeImporter::class)->import([['code' => 'OLD', 'name' => 'Again']], ['strategy' => 'skip_existing']);
        $this->assertSame(1, $skip['skipped']);

        $dup = app(ProductTypeImporter::class)->import([['code' => 'OLD']], ['strategy' => 'error_on_duplicate']);
        $this->assertSame('code', $dup['errors'][0]['field']);
    }

    public function test_materials_match_by_external_code_then_ean_then_code_and_generate_codes(): void
    {
        $type = MaterialType::firstOrCreate(['code' => 'RAW_MATERIAL'], ['name' => 'Raw material']);
        $byExternal = Material::factory()->create(['code' => 'M-1', 'external_code' => 'SUB-1', 'external_system' => 'subiekt', 'material_type_id' => $type->id]);
        $byEan = Material::factory()->create(['code' => 'M-2', 'ean' => '5901234123457', 'material_type_id' => $type->id]);

        $result = app(MaterialImporter::class)->import([
            ['external_code' => 'SUB-1', 'name' => 'Renamed via external code'],
            ['ean' => '5901234123457', 'name' => 'Renamed via EAN', 'unit_price' => '12,5'],
            ['external_code' => 'SUB-9', 'name' => 'Brand new bolt', 'material_type' => 'Raw material', 'stock_quantity' => 7],
            ['name' => 'no identifier'],
        ], ['strategy' => 'update_or_create', 'external_system' => 'subiekt']);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['updated']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(4, $result['errors'][0]['row']);

        $this->assertSame('Renamed via external code', $byExternal->fresh()->name);
        $this->assertSame('Renamed via EAN', $byEan->fresh()->name);
        $this->assertSame('M-2', $byEan->fresh()->code, 'an update never changes the code');

        $new = Material::where('external_code', 'SUB-9')->first();
        $this->assertNotNull($new);
        $this->assertSame('SUB-9', $new->code, 'code generated from the external code');
        $this->assertSame($type->id, $new->material_type_id, 'type matched by name');
        $this->assertEquals(7, (float) $new->stock_quantity);

        // update_only never creates.
        $only = app(MaterialImporter::class)->import([['code' => 'GHOST', 'name' => 'x']], ['strategy' => 'update_only']);
        $this->assertSame(1, $only['skipped']);
        $this->assertDatabaseMissing('materials', ['code' => 'GHOST']);
    }

    public function test_materials_default_type_option_applies_to_rows_without_one(): void
    {
        MaterialType::firstOrCreate(['code' => 'PACK'], ['name' => 'Packaging']);

        app(MaterialImporter::class)->import([['code' => 'BOX-1', 'name' => 'Box']], ['default_material_type' => 'PACK']);

        $this->assertSame('PACK', Material::where('code', 'BOX-1')->first()->materialType->code);
    }

    public function test_work_orders_loose_file_semantics(): void
    {
        $line = Line::factory()->create(['code' => 'L1']);
        $other = Line::factory()->create(['code' => 'L2']);
        $product = ProductType::factory()->create(['code' => 'P1']);
        ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        WorkOrder::factory()->create(['order_no' => 'DONE-1', 'status' => WorkOrder::STATUS_DONE, 'planned_qty' => 5]);

        $result = app(WorkOrderImporter::class)->import([
            ['order_no' => 'WO-1', 'quantity' => 10.0, 'line_code' => 'L1', 'product_type_code' => 'P1', 'product_name' => 'Widget', 'custom' => ['color' => 'red'], 'due_date' => '2026-12-01'],
            ['order_no' => 'WO-2', 'quantity' => 3.0],
            ['order_no' => 'WO-3', 'quantity' => 3.0, 'line_code' => 'NOPE'],
            ['order_no' => 'DONE-1', 'quantity' => 99.0],
            ['order_no' => '', 'quantity' => 1.0],
        ], ['strategy' => 'update_or_create']);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(1, $result['skipped'], 'a done order is never updated');
        $this->assertCount(2, $result['errors']);
        $this->assertSame([3, 5], array_column($result['errors'], 'row'));

        $wo = WorkOrder::where('order_no', 'WO-1')->first();
        $this->assertSame($line->id, $wo->line_id);
        $this->assertSame($product->id, $wo->product_type_id);
        $this->assertNotEmpty($wo->process_snapshot, 'active template is frozen onto the order');
        $this->assertSame(['color' => 'red', 'product_name' => 'Widget'], $wo->extra_data);
        $this->assertSame(WorkOrder::STATUS_PENDING, $wo->status);
        $this->assertNull(WorkOrder::where('order_no', 'WO-2')->first()->line_id);
        $this->assertEquals(5, (float) WorkOrder::where('order_no', 'DONE-1')->first()->planned_qty);

        // Target line overrides the column; planning period lands on every row.
        $again = app(WorkOrderImporter::class)->import(
            [['order_no' => 'WO-1', 'quantity' => 20.0, 'line_code' => 'L1', 'custom' => ['size' => 'L']]],
            ['strategy' => 'update_or_create', 'target_line_id' => $other->id, 'import_week' => 12, 'production_year' => 2026],
        );
        $this->assertSame(1, $again['updated']);
        $wo->refresh();
        $this->assertSame($other->id, $wo->line_id);
        $this->assertSame(12, (int) $wo->week_number);
        $this->assertSame(2026, (int) $wo->production_year);
        $this->assertSame(['color' => 'red', 'product_name' => 'Widget', 'size' => 'L'], $wo->extra_data);

        $skip = app(WorkOrderImporter::class)->import([['order_no' => 'WO-1', 'quantity' => 1.0]], ['strategy' => 'skip_existing']);
        $this->assertSame(1, $skip['skipped']);

        $dup = app(WorkOrderImporter::class)->import([['order_no' => 'WO-1', 'quantity' => 1.0]], ['strategy' => 'error_on_duplicate']);
        $this->assertSame('order_no', $dup['errors'][0]['field']);
    }

    public function test_boms_group_flat_rows_into_recipes_and_map_errors_to_file_rows(): void
    {
        $bread = ProductType::factory()->create(['code' => 'BREAD']);
        $cake = ProductType::factory()->create(['code' => 'CAKE']);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $bread->id, 'is_active' => true]);
        ProcessTemplate::factory()->create(['product_type_id' => $cake->id, 'is_active' => true]);
        Material::factory()->create(['code' => 'FLOUR']);
        Material::factory()->create(['code' => 'YEAST']);

        $result = app(BomImporter::class)->import([
            ['product_type_code' => 'BREAD', 'material_code' => 'FLOUR', 'quantity_per_unit' => 0.5],
            ['product_type_code' => 'BREAD', 'material_code' => 'YEAST', 'quantity_per_unit' => 0.01, 'scrap_percentage' => 2.0],
            ['product_type_code' => 'CAKE', 'material_code' => 'FLOUR', 'quantity_per_unit' => 0.3],
            ['product_type_code' => 'CAKE', 'material_code' => 'GHOST', 'quantity_per_unit' => 1.0],
            ['product_type_code' => '', 'material_code' => 'FLOUR', 'quantity_per_unit' => 1.0],
        ], ['mode' => 'replace']);

        $this->assertSame(1, $result['imported'], 'one recipe (bread) created');
        $this->assertSame([3, 5], array_column($result['errors'], 'row'), 'cake fails at the row that opened it');
        $this->assertSame(2, BomItem::where('process_template_id', $template->id)->count());
        $this->assertEquals(2.0, (float) BomItem::where('process_template_id', $template->id)->whereHas('material', fn ($q) => $q->where('code', 'YEAST'))->value('scrap_percentage'));
        $this->assertSame(0, BomItem::whereHas('processTemplate', fn ($q) => $q->where('product_type_id', $cake->id))->count(), 'a recipe with an unknown component is never half-applied');
    }

    public function test_the_material_type_filter_matches_the_column_the_importer_maps(): void
    {
        MaterialType::firstOrCreate(['code' => 'RAW_MATERIAL'], ['name' => 'Raw material']);
        MaterialType::firstOrCreate(['code' => 'CONSUMABLE'], ['name' => 'Consumable']);

        // The option is filtered by MaterialImportService, which reads the ERP
        // payload's `category`. The CSV importer declares no such field — it
        // maps the column onto `material_type` — so a filter that only looked
        // at `category` skipped every row of every file.
        $result = app(MaterialImporter::class)->import([
            ['code' => 'M-RAW', 'name' => 'Steel', 'material_type' => 'RAW_MATERIAL'],
            ['code' => 'M-CON', 'name' => 'Paint', 'material_type' => 'CONSUMABLE'],
        ], ['strategy' => 'update_or_create', 'only_categories' => 'raw_material']);

        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['imported'], 'the matching row is imported');
        $this->assertSame(1, $result['skipped'], 'the other type is skipped');
        $this->assertTrue(Material::where('code', 'M-RAW')->exists());
        $this->assertFalse(Material::where('code', 'M-CON')->exists());
    }

    public function test_samples_match_declared_fields(): void
    {
        foreach (app(ImportRegistry::class)->all() as $importer) {
            $sample = $importer->sample();
            $unknown = array_diff($sample['headers'], array_keys($importer->fields()));

            $this->assertSame([], $unknown, $importer->key().' sample headers must be importable fields');
            $this->assertSame([], $importer->missingIdentifiers(array_combine($sample['headers'], $sample['headers'])), $importer->key().' sample must map every required field');
            $this->assertNotEmpty($sample['rows']);
        }
    }
}
