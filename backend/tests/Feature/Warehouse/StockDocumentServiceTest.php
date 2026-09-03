<?php

namespace Tests\Feature\Warehouse;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\ProductType;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Warehouse\StockDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Posting and cancelling warehouse documents (#212) — the only path that moves
 * stock, so its arithmetic and its guards are asserted directly.
 */
class StockDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockDocumentService::class);
    }

    private function rawWarehouse(): Warehouse
    {
        return Warehouse::factory()->rawMaterial()->isDefault()->create(['code' => 'RAW-1']);
    }

    public function test_posting_a_material_issue_reduces_balances_and_writes_the_ledger(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create(['code' => 'FLOUR-01', 'stock_quantity' => 500]);

        WarehouseStock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'quantity' => 500,
        ]);

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 120.5, 'unit_of_measure' => 'kg']],
        ]);

        $this->assertTrue($document->isDraft());
        // A draft has not moved anything yet.
        $this->assertEquals(500, (float) $material->fresh()->stock_quantity);

        $this->service->post($document);

        $this->assertTrue($document->fresh()->isPosted());
        $this->assertEquals(379.5, (float) WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('material_id', $material->id)
            ->value('quantity'));
        // The global material balance follows, with an audit trail.
        $this->assertEquals(379.5, (float) $material->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => StockMovement::TYPE_CONSUME,
            'source_type' => StockMovement::SOURCE_STOCK_DOCUMENT,
            'source_id' => $document->id,
        ]);
    }

    public function test_posting_a_product_receipt_increases_the_finished_goods_balance(): void
    {
        $warehouse = Warehouse::factory()->finishedGoods()->create(['code' => 'FG-1']);
        $product = ProductType::factory()->create(['code' => 'BREAD-01']);

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_PRODUCT_RECEIPT,
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_type_id' => $product->id, 'quantity' => 240]],
        ]);

        $this->service->post($document);

        $this->assertEquals(240, (float) WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('product_type_id', $product->id)
            ->value('quantity'));
    }

    public function test_posting_a_lot_line_keeps_the_lot_and_the_warehouse_total_in_step(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create(['stock_quantity' => 100]);
        $lot = MaterialLot::factory()->create([
            'material_id' => $material->id,
            'quantity_available' => 100,
            'status' => MaterialLot::STATUS_RELEASED,
        ]);

        WarehouseStock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'material_lot_id' => $lot->id,
            'quantity' => 100,
        ]);
        WarehouseStock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'quantity' => 100,
        ]);

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'material_lot_id' => $lot->id, 'quantity' => 100]],
        ]);

        $this->service->post($document);

        // Both the lot row and the warehouse total drop; the drained lot is consumed.
        $this->assertEquals(0, (float) WarehouseStock::where('material_lot_id', $lot->id)->value('quantity'));
        $this->assertEquals(0, (float) WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('material_id', $material->id)
            ->whereNull('material_lot_id')
            ->value('quantity'));
        $this->assertEquals(0, (float) $lot->fresh()->quantity_available);
        $this->assertSame(MaterialLot::STATUS_CONSUMED, $lot->fresh()->status);
    }

    public function test_cancelling_a_posted_document_reverses_every_effect(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create(['stock_quantity' => 500]);

        WarehouseStock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'quantity' => 500,
        ]);

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 200]],
        ]);

        $this->service->post($document);
        $this->service->cancel($document->fresh());

        $this->assertSame(StockDocument::STATUS_CANCELLED, $document->fresh()->status);
        $this->assertEquals(500, (float) WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('material_id', $material->id)
            ->value('quantity'));
        $this->assertEquals(500, (float) $material->fresh()->stock_quantity);
        // Reversal is booked, not erased: two movements, netting to zero.
        $this->assertSame(2, StockMovement::where('source_id', $document->id)->count());
        $this->assertEquals(0, (float) StockMovement::where('source_id', $document->id)->sum('quantity'));
    }

    public function test_cancelling_a_draft_does_not_touch_stock(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create(['stock_quantity' => 50]);

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 10]],
        ]);

        $this->service->cancel($document);

        $this->assertEquals(50, (float) $material->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_posting_twice_does_not_double_count(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create(['stock_quantity' => 100]);

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 30]],
        ]);

        $this->service->post($document);
        $this->service->post($document->fresh());

        $this->assertEquals(70, (float) $material->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_a_cancelled_document_cannot_be_posted(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create();

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 5]],
        ]);

        $this->service->cancel($document);

        $this->expectException(ValidationException::class);
        $this->service->post($document->fresh());
    }

    public function test_a_document_cannot_be_posted_to_a_warehouse_of_the_wrong_kind(): void
    {
        $finishedGoods = Warehouse::factory()->finishedGoods()->create();
        $material = Material::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $finishedGoods->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 1]],
        ]);
    }

    public function test_creating_a_draft_without_lines_is_rejected(): void
    {
        $this->rawWarehouse();

        $this->expectException(ValidationException::class);

        $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'lines' => [],
        ]);
    }

    public function test_a_draft_falls_back_to_the_default_warehouse_for_its_kind(): void
    {
        $raw = $this->rawWarehouse();
        Warehouse::factory()->finishedGoods()->isDefault()->create();
        $material = Material::factory()->create();

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'lines' => [['material_id' => $material->id, 'quantity' => 1]],
        ]);

        $this->assertSame($raw->id, $document->warehouse_id);
    }

    public function test_posting_respects_the_block_negative_stock_setting(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'block_negative_stock'],
            ['value' => json_encode(true)],
        );

        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create(['code' => 'FLOUR-01', 'stock_quantity' => 10]);

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 50]],
        ]);

        try {
            $this->service->post($document);
            $this->fail('Posting below zero should have been blocked.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('FLOUR-01', collect($e->errors())->flatten()->implode(' '));
        }

        // The whole posting rolled back — no partial balance, still a draft.
        $this->assertEquals(10, (float) $material->fresh()->stock_quantity);
        $this->assertTrue($document->fresh()->isDraft());
    }

    /**
     * The plant can hold plenty of a material while the store this document issues
     * from holds none of it — either view being short has to stop the posting.
     */
    public function test_posting_is_blocked_when_the_warehouse_is_short_but_the_plant_is_not(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'block_negative_stock'],
            ['value' => json_encode(true)],
        );

        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create(['code' => 'FLOUR-02', 'stock_quantity' => 1000]);

        // Plenty in the plant, 5 in this store.
        \App\Models\WarehouseStock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'quantity' => 5,
        ]);

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 50]],
        ]);

        try {
            $this->service->post($document);
            $this->fail('Posting below the warehouse balance should have been blocked.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('FLOUR-02', collect($e->errors())->flatten()->implode(' '));
        }

        // Nothing moved: not the location balance, not the plant-wide quantity.
        $this->assertEquals(5.0, (float) \App\Models\WarehouseStock::where([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'material_lot_id' => null,
        ])->value('quantity'));
        $this->assertEquals(1000.0, (float) $material->fresh()->stock_quantity);
        $this->assertTrue($document->fresh()->isDraft());
    }

    public function test_document_numbers_are_sequential_per_type_and_year(): void
    {
        $this->rawWarehouse();
        $material = Material::factory()->create();

        $first = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'lines' => [['material_id' => $material->id, 'quantity' => 1]],
        ]);
        $second = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'lines' => [['material_id' => $material->id, 'quantity' => 1]],
        ]);

        $year = now()->year;
        $this->assertSame("MI/{$year}/0001", $first->document_no);
        $this->assertSame("MI/{$year}/0002", $second->document_no);
    }

    public function test_a_product_line_on_a_material_document_is_dropped(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create();
        $product = ProductType::factory()->create();

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [[
                'material_id' => $material->id,
                // Smuggled in from a hand-built payload — must not reach the row.
                'product_type_id' => $product->id,
                'quantity' => 5,
            ]],
        ]);

        $this->assertNull($document->lines->first()->product_type_id);
        $this->assertSame($material->id, $document->lines->first()->material_id);
    }

    public function test_soft_deleting_a_document_cascades_to_its_lines(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create();

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 5]],
        ]);
        $lineId = $document->lines->first()->id;

        $document->delete();

        $this->assertSoftDeleted('stock_documents', ['id' => $document->id]);
        $this->assertSoftDeleted('stock_document_lines', ['id' => $lineId]);

        $document->restore();

        $this->assertDatabaseHas('stock_document_lines', ['id' => $lineId, 'deleted_at' => null]);
    }

    public function test_a_lot_belonging_to_another_material_is_left_alone(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create(['stock_quantity' => 100]);
        $otherMaterial = Material::factory()->create();
        $foreignLot = MaterialLot::factory()->create([
            'material_id' => $otherMaterial->id,
            'quantity_available' => 500,
            'status' => MaterialLot::STATUS_RELEASED,
        ]);

        // The form request rejects this pairing; a payload assembled elsewhere must
        // not be able to draw down an unrelated lot either.
        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [[
                'material_id' => $material->id,
                'material_lot_id' => $foreignLot->id,
                'quantity' => 40,
            ]],
        ]);

        $this->service->post($document);

        $this->assertEquals(500, (float) $foreignLot->fresh()->quantity_available);
        // The material's own stock still moved — only the foreign lot was spared.
        $this->assertEquals(60, (float) $material->fresh()->stock_quantity);
    }

    public function test_document_numbers_survive_a_collision(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create();

        // Squat on the number the next create would generate.
        $year = now()->year;
        StockDocument::factory()->create([
            'document_no' => "MI/{$year}/0001",
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
        ]);

        $document = $this->service->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => $warehouse->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 1]],
        ]);

        $this->assertSame("MI/{$year}/0002", $document->document_no);
    }

    public function test_a_balance_row_must_name_exactly_one_item(): void
    {
        $warehouse = $this->rawWarehouse();
        $material = Material::factory()->create();
        $product = ProductType::factory()->create();

        // Both set: one view would count it, another would miss it.
        $this->expectException(\InvalidArgumentException::class);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'product_type_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_a_balance_row_must_name_at_least_one_item(): void
    {
        $warehouse = $this->rawWarehouse();

        $this->expectException(\InvalidArgumentException::class);

        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'quantity' => 1]);
    }

    public function test_a_lot_balance_row_must_carry_its_material(): void
    {
        $warehouse = $this->rawWarehouse();
        $lot = MaterialLot::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'material_lot_id' => $lot->id,
            'quantity' => 1,
        ]);
    }
}
