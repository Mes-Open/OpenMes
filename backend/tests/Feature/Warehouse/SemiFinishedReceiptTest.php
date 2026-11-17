<?php

namespace Tests\Feature\Warehouse;

use App\Models\Batch;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\StockDocument;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Warehouse\StockDocumentService;
use App\Services\Warehouse\WorkOrderStockDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A work order that makes a semi-finished part (a product type some material is
 * produced by) books its output as that material, so the next BOM level, MRP and
 * lot genealogy see it. A product with no material keeps the product receipt.
 */
class SemiFinishedReceiptTest extends TestCase
{
    use RefreshDatabase;

    private WorkOrderStockDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkOrderStockDocumentService::class);
        Warehouse::factory()->rawMaterial()->isDefault()->create(['code' => 'RAW-1']);
        Warehouse::factory()->finishedGoods()->isDefault()->create(['code' => 'FG-1']);
    }

    private function enableLotTracking(bool $on = true): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode($on)],
        );
    }

    /** A product type with a template, and the material that template produces. */
    private function semiFinished(string $code = 'PP-1.914'): array
    {
        $product = ProductType::factory()->create(['code' => $code, 'unit_of_measure' => 'pcs']);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        $material = Material::factory()->manufactured($template->id)->create([
            'code' => $code,
            'unit_of_measure' => 'pcs',
            'stock_quantity' => 0,
        ]);

        return [$product, $material];
    }

    private function workOrder(ProductType $product, float $produced): WorkOrder
    {
        return WorkOrder::factory()->create([
            'line_id' => Line::factory(),
            'product_type_id' => $product->id,
            'planned_qty' => 100,
            'produced_qty' => $produced,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_semi_finished_output_is_received_as_its_material_not_as_a_product(): void
    {
        [$product, $material] = $this->semiFinished();
        $workOrder = $this->workOrder($product, 80);

        $this->service->generateForCompletion($workOrder);

        $this->assertFalse(StockDocument::where('work_order_id', $workOrder->id)
            ->where('type', StockDocument::TYPE_PRODUCT_RECEIPT)->exists());

        $receipt = StockDocument::where('work_order_id', $workOrder->id)
            ->where('type', StockDocument::TYPE_MATERIAL_RECEIPT)
            ->with('lines')
            ->sole();

        $this->assertTrue($receipt->isDraft());
        $this->assertSame('RAW-1', $receipt->warehouse->code);
        $this->assertSame($material->id, $receipt->lines->sole()->material_id);
        $this->assertEquals(80.0, (float) $receipt->lines->sole()->quantity);
        // A draft moves nothing.
        $this->assertEquals(0.0, (float) $material->fresh()->stock_quantity);
    }

    public function test_posting_the_receipt_raises_the_material_stock(): void
    {
        [$product, $material] = $this->semiFinished();
        $workOrder = $this->workOrder($product, 80);

        $this->service->generateForCompletion($workOrder);
        $receipt = StockDocument::where('type', StockDocument::TYPE_MATERIAL_RECEIPT)->sole();

        app(StockDocumentService::class)->post($receipt);

        $this->assertEquals(80.0, (float) $material->fresh()->stock_quantity);
    }

    public function test_with_lot_tracking_each_batch_becomes_a_released_lot_linked_to_it(): void
    {
        $this->enableLotTracking();
        [$product, $material] = $this->semiFinished();
        $workOrder = $this->workOrder($product, 80);
        $first = Batch::factory()->create([
            'work_order_id' => $workOrder->id, 'batch_number' => 1, 'produced_qty' => 50, 'lot_number' => 'L-0001',
        ]);
        $second = Batch::factory()->create([
            'work_order_id' => $workOrder->id, 'batch_number' => 2, 'produced_qty' => 30, 'lot_number' => null,
        ]);

        $this->service->generateForCompletion($workOrder);
        $receipt = StockDocument::where('type', StockDocument::TYPE_MATERIAL_RECEIPT)->with('lines')->sole();

        $this->assertCount(2, $receipt->lines);

        $lotOfFirst = MaterialLot::where('source_batch_id', $first->id)->sole();
        $this->assertSame('L-0001', $lotOfFirst->lot_number);
        $this->assertSame($material->id, $lotOfFirst->material_id);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lotOfFirst->status);
        $this->assertEquals(50.0, (float) $lotOfFirst->quantity_received);
        // Nothing is available before the receipt is posted.
        $this->assertEquals(0.0, (float) $lotOfFirst->quantity_available);

        $lotOfSecond = MaterialLot::where('source_batch_id', $second->id)->sole();
        $this->assertSame($workOrder->order_no.'-2', $lotOfSecond->lot_number);

        app(StockDocumentService::class)->post($receipt);

        $this->assertEquals(50.0, (float) $lotOfFirst->fresh()->quantity_available);
        $this->assertEquals(30.0, (float) $lotOfSecond->fresh()->quantity_available);
        $this->assertTrue($lotOfFirst->fresh()->isAvailable());
        $this->assertEquals(80.0, (float) $material->fresh()->stock_quantity);
    }

    public function test_a_batch_lot_number_already_taken_gets_a_unique_suffix(): void
    {
        $this->enableLotTracking();
        [$product, $material] = $this->semiFinished();
        MaterialLot::factory()->create(['lot_number' => 'L-0001']);
        $workOrder = $this->workOrder($product, 10);
        $batch = Batch::factory()->create([
            'work_order_id' => $workOrder->id, 'batch_number' => 1, 'produced_qty' => 10, 'lot_number' => 'L-0001',
        ]);

        $this->service->generateForCompletion($workOrder);

        $lot = MaterialLot::where('source_batch_id', $batch->id)->sole();
        $this->assertSame('L-0001-'.$batch->id, $lot->lot_number);
    }

    public function test_without_lot_tracking_no_lot_is_created(): void
    {
        $this->enableLotTracking(false);
        [$product] = $this->semiFinished();
        $workOrder = $this->workOrder($product, 40);
        Batch::factory()->create(['work_order_id' => $workOrder->id, 'batch_number' => 1, 'produced_qty' => 40]);

        $this->service->generateForCompletion($workOrder);

        $receipt = StockDocument::where('type', StockDocument::TYPE_MATERIAL_RECEIPT)->with('lines')->sole();
        $this->assertNull($receipt->lines->sole()->material_lot_id);
        $this->assertEquals(40.0, (float) $receipt->lines->sole()->quantity);
        $this->assertSame(0, MaterialLot::count());
    }

    public function test_generation_stays_idempotent_for_semi_finished_orders(): void
    {
        $this->enableLotTracking();
        [$product] = $this->semiFinished();
        $workOrder = $this->workOrder($product, 10);
        Batch::factory()->create(['work_order_id' => $workOrder->id, 'batch_number' => 1, 'produced_qty' => 10]);

        $this->service->generateForCompletion($workOrder);
        $second = $this->service->generateForCompletion($workOrder);

        $this->assertSame([], $second);
        $this->assertSame(1, StockDocument::where('type', StockDocument::TYPE_MATERIAL_RECEIPT)->count());
        $this->assertSame(1, MaterialLot::count());
    }

    public function test_a_product_with_no_material_keeps_the_product_receipt(): void
    {
        $product = ProductType::factory()->create();
        ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
        $workOrder = $this->workOrder($product, 25);

        $this->service->generateForCompletion($workOrder);

        $this->assertTrue(StockDocument::where('type', StockDocument::TYPE_PRODUCT_RECEIPT)->exists());
        $this->assertFalse(StockDocument::where('type', StockDocument::TYPE_MATERIAL_RECEIPT)->exists());
    }

    public function test_two_materials_produced_by_one_product_is_ambiguous_and_keeps_the_product_receipt(): void
    {
        [$product] = $this->semiFinished('PP-1.029');
        $otherTemplate = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true, 'version' => 2]);
        Material::factory()->manufactured($otherTemplate->id)->create(['code' => 'PP-1.029-B']);
        $workOrder = $this->workOrder($product, 25);

        $this->service->generateForCompletion($workOrder);

        $this->assertTrue(StockDocument::where('type', StockDocument::TYPE_PRODUCT_RECEIPT)->exists());
        $this->assertFalse(StockDocument::where('type', StockDocument::TYPE_MATERIAL_RECEIPT)->exists());
    }

    public function test_every_technology_of_the_product_maps_to_the_same_material(): void
    {
        // The material names one technology as its producer; an order made on
        // another technology of the same product is still that material.
        [$product, $material] = $this->semiFinished();
        $otherTechnology = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true, 'version' => 2]);
        $workOrder = $this->workOrder($product, 15);
        $workOrder->bomTemplates()->attach($otherTechnology->id, ['sort_order' => 0]);

        $this->service->generateForCompletion($workOrder);

        $receipt = StockDocument::where('type', StockDocument::TYPE_MATERIAL_RECEIPT)->with('lines')->sole();
        $this->assertSame($material->id, $receipt->lines->sole()->material_id);
    }

    public function test_cancelling_a_posted_receipt_empties_the_lot_again(): void
    {
        $this->enableLotTracking();
        [$product, $material] = $this->semiFinished();
        $workOrder = $this->workOrder($product, 20);
        $batch = Batch::factory()->create(['work_order_id' => $workOrder->id, 'batch_number' => 1, 'produced_qty' => 20]);

        $this->service->generateForCompletion($workOrder);
        $receipt = StockDocument::where('type', StockDocument::TYPE_MATERIAL_RECEIPT)->sole();
        $documents = app(StockDocumentService::class);
        $documents->post($receipt);
        $documents->cancel($receipt->fresh());

        $lot = MaterialLot::where('source_batch_id', $batch->id)->sole();
        $this->assertEquals(0.0, (float) $lot->quantity_available);
        $this->assertSame(MaterialLot::STATUS_CONSUMED, $lot->status);
        $this->assertEquals(0.0, (float) $material->fresh()->stock_quantity);
    }

    public function test_a_semi_finished_order_packed_on_pallets_leaves_the_receipt_to_the_pallets(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'pallet_stock_documents'], ['value' => json_encode('draft')]);
        [$product] = $this->semiFinished();
        $workOrder = $this->workOrder($product, 20);
        $batch = Batch::factory()->create(['work_order_id' => $workOrder->id, 'batch_number' => 1, 'produced_qty' => 20]);
        \App\Models\BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'kind' => \App\Models\TemplateStep::KIND_PACKING]);

        $this->service->generateForCompletion($workOrder);

        $this->assertFalse(StockDocument::where('type', StockDocument::TYPE_MATERIAL_RECEIPT)->exists());
        $this->assertFalse(StockDocument::where('type', StockDocument::TYPE_PRODUCT_RECEIPT)->exists());
    }

    public function test_the_suffixed_lot_number_is_checked_too(): void
    {
        $this->enableLotTracking();
        [$product] = $this->semiFinished();
        $workOrder = $this->workOrder($product, 10);
        $batch = Batch::factory()->create([
            'work_order_id' => $workOrder->id, 'batch_number' => 1, 'produced_qty' => 10, 'lot_number' => 'L-0002',
        ]);
        MaterialLot::factory()->create(['lot_number' => 'L-0002']);
        MaterialLot::factory()->create(['lot_number' => 'L-0002-'.$batch->id]);

        $this->service->generateForCompletion($workOrder);

        $this->assertSame('L-0002-'.$batch->id.'-2', MaterialLot::where('source_batch_id', $batch->id)->sole()->lot_number);
    }
}
