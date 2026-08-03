<?php

namespace Tests\Feature\Warehouse;

use App\Models\BomItem;
use App\Models\Line;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\StockDocument;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Warehouse\WorkOrderStockDocumentService;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Draft warehouse paperwork generated from production (#212): a material release
 * for what a work order consumed and a product receipt for what it produced.
 */
class WorkOrderStockDocumentTest extends TestCase
{
    use RefreshDatabase;

    private WorkOrderStockDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkOrderStockDocumentService::class);
    }

    private function workOrderWithRecipe(float $planned = 100, float $produced = 0): WorkOrder
    {
        $product = ProductType::factory()->create(['code' => 'BREAD-01', 'unit_of_measure' => 'pcs']);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        $flour = Material::factory()->create(['code' => 'FLOUR-01', 'unit_of_measure' => 'kg']);

        BomItem::create([
            'process_template_id' => $template->id,
            'material_id' => $flour->id,
            'quantity_per_unit' => 0.5,
            'scrap_percentage' => 10,
        ]);

        return WorkOrder::factory()->create([
            'line_id' => Line::factory(),
            'product_type_id' => $product->id,
            'planned_qty' => $planned,
            'produced_qty' => $produced,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_completion_generates_a_material_release_and_a_product_receipt(): void
    {
        Warehouse::factory()->rawMaterial()->isDefault()->create(['code' => 'RAW-1']);
        Warehouse::factory()->finishedGoods()->isDefault()->create(['code' => 'FG-1']);

        $workOrder = $this->workOrderWithRecipe(planned: 100, produced: 80);

        $created = $this->service->generateForCompletion($workOrder);

        $this->assertCount(2, $created);

        $issue = StockDocument::where('work_order_id', $workOrder->id)
            ->where('type', StockDocument::TYPE_MATERIAL_ISSUE)
            ->with('lines')
            ->first();

        // 0.5 kg per unit + 10% scrap, times the 80 produced.
        $this->assertEquals(44.0, (float) $issue->lines->first()->quantity);
        $this->assertTrue($issue->isDraft());

        $receipt = StockDocument::where('work_order_id', $workOrder->id)
            ->where('type', StockDocument::TYPE_PRODUCT_RECEIPT)
            ->with('lines')
            ->first();

        $this->assertEquals(80.0, (float) $receipt->lines->first()->quantity);
        $this->assertSame($workOrder->product_type_id, $receipt->lines->first()->product_type_id);
        // Drafts only: generation must never move stock on its own.
        $this->assertTrue($receipt->isDraft());
    }

    public function test_generation_is_idempotent(): void
    {
        Warehouse::factory()->rawMaterial()->isDefault()->create();
        Warehouse::factory()->finishedGoods()->isDefault()->create();

        $workOrder = $this->workOrderWithRecipe(produced: 10);

        $this->service->generateForCompletion($workOrder);
        $second = $this->service->generateForCompletion($workOrder);

        $this->assertSame([], $second);
        $this->assertSame(2, StockDocument::where('work_order_id', $workOrder->id)->count());
    }

    public function test_nothing_is_generated_without_a_warehouse(): void
    {
        $workOrder = $this->workOrderWithRecipe(produced: 10);

        $this->assertSame([], $this->service->generateForCompletion($workOrder));
        $this->assertSame(0, StockDocument::count());
    }

    public function test_planned_quantity_stands_in_when_nothing_was_counted(): void
    {
        Warehouse::factory()->finishedGoods()->isDefault()->create();

        $workOrder = $this->workOrderWithRecipe(planned: 60, produced: 0);

        $receipt = $this->service->generateProductReceipt($workOrder);

        $this->assertEquals(60.0, (float) $receipt->lines->first()->quantity);
    }

    public function test_a_work_order_without_a_recipe_gets_no_material_release(): void
    {
        Warehouse::factory()->rawMaterial()->isDefault()->create();

        $product = ProductType::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'line_id' => Line::factory(),
            'product_type_id' => $product->id,
            'planned_qty' => 10,
        ]);

        $this->assertNull($this->service->generateMaterialIssue($workOrder));
    }

    public function test_completing_a_work_order_generates_documents_through_the_listener(): void
    {
        // The listener is wired to WorkOrderCompleted; the module gate must be on.
        ModuleRegistry::save(array_merge(ModuleRegistry::enabled(), ['warehouse']));

        Warehouse::factory()->rawMaterial()->isDefault()->create();
        Warehouse::factory()->finishedGoods()->isDefault()->create();

        $workOrder = $this->workOrderWithRecipe(planned: 100, produced: 100);

        $workOrder->update(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);

        $this->assertSame(2, StockDocument::where('work_order_id', $workOrder->id)->count());
    }

    public function test_the_listener_stays_out_of_the_way_when_the_module_is_off(): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::enabled(), ['warehouse'])));

        Warehouse::factory()->rawMaterial()->isDefault()->create();
        Warehouse::factory()->finishedGoods()->isDefault()->create();

        $workOrder = $this->workOrderWithRecipe(planned: 100, produced: 100);

        $workOrder->update(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);

        $this->assertSame(0, StockDocument::count());
    }

    public function test_generation_can_be_switched_off_by_config(): void
    {
        ModuleRegistry::save(array_merge(ModuleRegistry::enabled(), ['warehouse']));
        config(['openmmes.warehouse.auto_documents' => false]);

        Warehouse::factory()->rawMaterial()->isDefault()->create();
        Warehouse::factory()->finishedGoods()->isDefault()->create();

        $workOrder = $this->workOrderWithRecipe(planned: 5, produced: 5);
        $workOrder->update(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);

        $this->assertSame(0, StockDocument::count());
    }
}
