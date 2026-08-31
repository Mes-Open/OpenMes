<?php

namespace Tests\Feature\Warehouse;

use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use App\Services\Material\ConsumptionLocationService;
use App\Services\Material\MaterialAllocationService;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deducting shop-floor consumption from the location it was taken from.
 *
 * Allocation already moves the plant-wide quantity and the lot; what is asserted here
 * is the per-location balance, which nothing used to touch.
 */
class ConsumptionLocationTest extends TestCase
{
    use RefreshDatabase;

    private ConsumptionLocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConsumptionLocationService::class);
    }

    private function warehouse(string $code, bool $default = false): Warehouse
    {
        $factory = Warehouse::factory()->rawMaterial();

        if ($default) {
            $factory = $factory->isDefault();
        }

        return $factory->create(['code' => $code]);
    }

    private function stockAt(Warehouse $warehouse, Material $material, float $qty, ?MaterialLot $lot = null): WarehouseStock
    {
        return WarehouseStock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'material_lot_id' => $lot?->id,
            'quantity' => $qty,
        ]);
    }

    private function balance(Warehouse $warehouse, Material $material, ?MaterialLot $lot = null): float
    {
        return (float) WarehouseStock::where([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'material_lot_id' => $lot?->id,
        ])->value('quantity');
    }

    private function blockNegativeStock(bool $on): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'block_negative_stock'],
            ['value' => json_encode($on)],
        );
    }

    /** An allocation on a line pointed at the given warehouse. */
    private function allocationOnLine(Warehouse $warehouse, Material $material, float $allocated = 100): MaterialAllocation
    {
        $line = Line::factory()->create(['warehouse_id' => $warehouse->id]);
        $workOrder = WorkOrder::factory()->create(['line_id' => $line->id]);

        return MaterialAllocation::factory()->create([
            'material_id' => $material->id,
            'work_order_id' => $workOrder->id,
            'batch_id' => \App\Models\Batch::factory()->create(['work_order_id' => $workOrder->id])->id,
            'allocated_qty' => $allocated,
        ]);
    }

    // ── Deduction ─────────────────────────────────────────────────────────────

    public function test_recording_consumption_deducts_from_the_line_location(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create(['code' => 'FLOUR-01']);
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);

        $this->service->deduct($allocation, 120.5);

        $this->assertEquals(379.5, $this->balance($warehouse, $material));
        $this->assertEquals(120.5, (float) $allocation->fresh()->location_deducted_qty);
        $this->assertSame($warehouse->id, $allocation->fresh()->consumption_warehouse_id);
    }

    public function test_the_deduction_is_auditable_as_a_movement_carrying_the_location(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $this->service->deduct($allocation, 40);

        $movement = StockMovement::where('material_id', $material->id)
            ->where('movement_type', StockMovement::TYPE_CONSUME)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($warehouse->id, $movement->warehouse_id);
        $this->assertEquals(-40, (float) $movement->quantity);
        $this->assertStringContainsString('Consumed on batch', $movement->reason);
    }

    /**
     * The plant-wide quantity already went down at allocation; moving it again here
     * would count the same material twice.
     */
    public function test_the_deduction_does_not_move_the_plant_wide_quantity_again(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create(['stock_quantity' => 500]);
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $this->service->deduct($allocation, 40);

        $this->assertEquals(500, (float) $material->fresh()->stock_quantity);
    }

    public function test_repeated_recording_moves_only_the_difference(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);

        $this->service->deduct($allocation, 30);
        $this->service->deduct($allocation, 50);

        // 50 consumed in total, not 80.
        $this->assertEquals(450.0, $this->balance($warehouse, $material));
        $this->assertEquals(50.0, (float) $allocation->fresh()->location_deducted_qty);
    }

    public function test_correcting_consumption_down_credits_the_location_back(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);

        $this->service->deduct($allocation, 80);
        $this->service->deduct($allocation, 30);

        $this->assertEquals(470.0, $this->balance($warehouse, $material));

        $credit = StockMovement::where('material_id', $material->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->latest('id')
            ->firstOrFail();
        $this->assertEquals(50, (float) $credit->quantity);
    }

    public function test_an_unchanged_quantity_writes_nothing(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $this->service->deduct($allocation, 25);
        $movements = StockMovement::count();

        $this->assertSame([], $this->service->deduct($allocation, 25));
        $this->assertSame($movements, StockMovement::count());
    }

    // ── Location selection ────────────────────────────────────────────────────

    public function test_a_picked_lot_decides_the_location_over_the_line(): void
    {
        $lineWarehouse = $this->warehouse('WS-LINE');
        $lotWarehouse = $this->warehouse('WS-LOT');
        $material = Material::factory()->create();

        $lot = MaterialLot::factory()->create([
            'material_id' => $material->id,
            'warehouse_id' => $lotWarehouse->id,
        ]);

        $this->stockAt($lineWarehouse, $material, 500);
        $this->stockAt($lotWarehouse, $material, 500);
        $this->stockAt($lotWarehouse, $material, 500, $lot);

        $allocation = $this->allocationOnLine($lineWarehouse, $material);
        $allocation->lotPicks()->create(['material_lot_id' => $lot->id, 'picked_qty' => 100]);

        $this->service->deduct($allocation->fresh(), 60);

        // The lot knows exactly where it sits; the line is only the fallback.
        $this->assertEquals(440.0, $this->balance($lotWarehouse, $material));
        $this->assertEquals(500.0, $this->balance($lineWarehouse, $material));
        // The lot-level balance follows the material total for that location.
        $this->assertEquals(440.0, $this->balance($lotWarehouse, $material, $lot));
    }

    public function test_the_line_location_is_used_when_no_lot_was_picked(): void
    {
        $lineWarehouse = $this->warehouse('WS-LINE');
        $this->warehouse('WS-DEFAULT', default: true);
        $material = Material::factory()->create();
        $this->stockAt($lineWarehouse, $material, 500);

        $allocation = $this->allocationOnLine($lineWarehouse, $material);
        $this->service->deduct($allocation, 70);

        $this->assertEquals(430.0, $this->balance($lineWarehouse, $material));
    }

    public function test_the_default_location_is_the_last_resort(): void
    {
        $default = $this->warehouse('WS-DEFAULT', default: true);
        $material = Material::factory()->create();
        $this->stockAt($default, $material, 500);

        // A line with no warehouse of its own.
        $line = Line::factory()->create(['warehouse_id' => null]);
        $workOrder = WorkOrder::factory()->create(['line_id' => $line->id]);
        $allocation = MaterialAllocation::factory()->create([
            'material_id' => $material->id,
            'work_order_id' => $workOrder->id,
            'batch_id' => \App\Models\Batch::factory()->create(['work_order_id' => $workOrder->id])->id,
        ]);

        $this->service->deduct($allocation, 25);

        $this->assertEquals(475.0, $this->balance($default, $material));
    }

    public function test_a_plant_with_no_locations_at_all_is_left_alone(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 100]);
        $line = Line::factory()->create(['warehouse_id' => null]);
        $workOrder = WorkOrder::factory()->create(['line_id' => $line->id]);
        $allocation = MaterialAllocation::factory()->create([
            'material_id' => $material->id,
            'work_order_id' => $workOrder->id,
            'batch_id' => \App\Models\Batch::factory()->create(['work_order_id' => $workOrder->id])->id,
        ]);

        $this->assertSame([], $this->service->deduct($allocation, 25));
        $this->assertSame(0, WarehouseStock::count());
        $this->assertEquals(0.0, (float) $allocation->fresh()->location_deducted_qty);
    }

    public function test_the_deduction_stays_out_of_the_way_when_the_module_is_off(): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::enabled(), ['warehouse'])));

        $warehouse = $this->warehouse('WS-1', default: true);
        $material = Material::factory()->create();
        $stock = $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);

        // Balances nobody maintains any more must neither move nor refuse production.
        $this->assertSame([], $this->service->deduct($allocation, 120));
        $this->assertEquals(500.0, (float) $stock->fresh()->quantity);
        $this->assertEquals(0.0, (float) $allocation->fresh()->location_deducted_qty);
        $this->assertNull($allocation->fresh()->consumption_warehouse_id);
    }

    /**
     * A correction must credit back the location the material actually left, even
     * after the lot has been moved somewhere else.
     */
    public function test_the_location_is_frozen_once_something_has_been_deducted(): void
    {
        $original = $this->warehouse('WS-1');
        $moved = $this->warehouse('WS-2');
        $material = Material::factory()->create();
        $this->stockAt($original, $material, 500);
        $this->stockAt($moved, $material, 500);

        $line = Line::factory()->create(['warehouse_id' => $original->id]);
        $workOrder = WorkOrder::factory()->create(['line_id' => $line->id]);
        $allocation = MaterialAllocation::factory()->create([
            'material_id' => $material->id,
            'work_order_id' => $workOrder->id,
            'batch_id' => \App\Models\Batch::factory()->create(['work_order_id' => $workOrder->id])->id,
        ]);

        $this->service->deduct($allocation, 100);

        // The line is re-pointed at another store after the fact.
        $line->update(['warehouse_id' => $moved->id]);
        $this->service->deduct($allocation->fresh(), 60);

        $this->assertEquals(440.0, $this->balance($original, $material));
        $this->assertEquals(500.0, $this->balance($moved, $material));
    }

    // ── Insufficient stock ────────────────────────────────────────────────────

    public function test_consumption_beyond_the_location_balance_is_refused_when_the_plant_blocks_it(): void
    {
        $this->blockNegativeStock(true);

        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create(['code' => 'FLOUR-01']);
        $this->stockAt($warehouse, $material, 10);

        $allocation = $this->allocationOnLine($warehouse, $material);

        $this->expectException(\DomainException::class);

        try {
            $this->service->deduct($allocation, 40);
        } finally {
            // Nothing moved and nothing was booked as deducted.
            $this->assertEquals(10.0, $this->balance($warehouse, $material));
            $this->assertEquals(0.0, (float) $allocation->fresh()->location_deducted_qty);
        }
    }

    public function test_the_same_consumption_is_allowed_and_flagged_when_the_plant_does_not_block_it(): void
    {
        $this->blockNegativeStock(false);

        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 10);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $movement = $this->service->deduct($allocation, 40)[0];

        // Production is not stopped, but the overdraw is on the record.
        $this->assertEquals(-30.0, $this->balance($warehouse, $material));
        $this->assertStringContainsString('SHORTFALL', $movement->reason);
        $this->assertStringContainsString('10 of 40', $movement->reason);
    }

    public function test_a_location_that_holds_exactly_enough_is_not_a_shortfall(): void
    {
        $this->blockNegativeStock(true);

        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 40);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $movement = $this->service->deduct($allocation, 40)[0];

        $this->assertEquals(0.0, $this->balance($warehouse, $material));
        $this->assertStringNotContainsString('SHORTFALL', $movement->reason);
    }

    // ── Through the allocation service ────────────────────────────────────────

    public function test_recording_consumption_through_the_allocation_service_deducts_once(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $allocations = app(MaterialAllocationService::class);

        $allocations->recordConsumption($allocation, 60);
        $this->assertEquals(440.0, $this->balance($warehouse, $material));

        // Batch completion finalises the same quantity — it must not deduct again.
        $allocations->consumeForBatch($allocation->batch);
        $this->assertEquals(440.0, $this->balance($warehouse, $material));
    }

    public function test_batch_completion_without_an_operator_entry_deducts_the_allocated_quantity(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material, allocated: 75);

        app(MaterialAllocationService::class)->consumeForBatch($allocation->batch);

        $this->assertEquals(425.0, $this->balance($warehouse, $material));
    }

    public function test_scrap_is_taken_off_the_location_together_with_what_was_used(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $allocations = app(MaterialAllocationService::class);

        // 60 used + 5 spoiled: both left the store, only the rest stayed behind.
        $allocations->recordConsumption($allocation, 60, scrap: 5);

        $this->assertEquals(435.0, $this->balance($warehouse, $material));
        $this->assertEquals(65.0, (float) $allocation->fresh()->location_deducted_qty);

        // Batch completion finalises the same consumed + scrap pair: no second bite.
        $allocations->consumeForBatch($allocation->batch);

        $this->assertEquals(435.0, $this->balance($warehouse, $material));
    }

    public function test_cancelling_a_batch_gives_back_the_scrap_it_took_too(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $allocations = app(MaterialAllocationService::class);

        $allocations->recordConsumption($allocation, 60, scrap: 5);
        $allocations->returnForBatch($allocation->batch);

        $this->assertEquals(500.0, $this->balance($warehouse, $material));
        $this->assertEquals(0.0, (float) $allocation->fresh()->location_deducted_qty);
    }

    /**
     * Lot picking is FEFO across the material's lots and knows nothing about stores,
     * so one allocation can legitimately draw from two — and neither may be charged
     * for what the other gave up.
     */
    public function test_picks_spanning_two_locations_are_split_between_them(): void
    {
        $lineWarehouse = $this->warehouse('WS-LINE');
        $first = $this->warehouse('WS-A');
        $second = $this->warehouse('WS-B');
        $material = Material::factory()->create();

        $lotA = MaterialLot::factory()->create(['material_id' => $material->id, 'warehouse_id' => $first->id]);
        $lotB = MaterialLot::factory()->create(['material_id' => $material->id, 'warehouse_id' => $second->id]);

        $this->stockAt($first, $material, 500);
        $this->stockAt($first, $material, 500, $lotA);
        $this->stockAt($second, $material, 500);
        $this->stockAt($second, $material, 500, $lotB);

        $allocation = $this->allocationOnLine($lineWarehouse, $material);
        $allocation->lotPicks()->create(['material_lot_id' => $lotA->id, 'picked_qty' => 75]);
        $allocation->lotPicks()->create(['material_lot_id' => $lotB->id, 'picked_qty' => 25]);

        $movements = $this->service->deduct($allocation->fresh(), 40);

        // 75/25 of the picked quantity, so 30/10 of the consumption.
        $this->assertEquals(470.0, $this->balance($first, $material));
        $this->assertEquals(470.0, $this->balance($first, $material, $lotA));
        $this->assertEquals(490.0, $this->balance($second, $material));
        $this->assertEquals(490.0, $this->balance($second, $material, $lotB));

        // One ledger row per location, each naming its own.
        $this->assertCount(2, $movements);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            collect($movements)->pluck('warehouse_id')->all(),
        );
    }

    /**
     * A lot that has been moved between the deduction and the correction must still
     * credit back the store that gave the material up — the pick freezes its location
     * the first time it is deducted, exactly as the allocation does.
     */
    public function test_a_moved_lot_still_credits_back_the_store_it_left(): void
    {
        $original = $this->warehouse('WS-A');
        $moved = $this->warehouse('WS-B');
        $material = Material::factory()->create();

        $lot = MaterialLot::factory()->create([
            'material_id' => $material->id,
            'warehouse_id' => $original->id,
        ]);

        $this->stockAt($original, $material, 500);
        $this->stockAt($original, $material, 500, $lot);
        $this->stockAt($moved, $material, 500);

        $allocation = $this->allocationOnLine($original, $material);
        $allocation->lotPicks()->create(['material_lot_id' => $lot->id, 'picked_qty' => 100]);

        $this->service->deduct($allocation->fresh(), 40);
        $this->assertEquals(460.0, $this->balance($original, $material));

        // The lot is transferred to another store, then the entry is corrected down.
        $lot->update(['warehouse_id' => $moved->id]);
        $this->service->deduct($allocation->fresh(), 10);

        $this->assertEquals(490.0, $this->balance($original, $material));
        $this->assertEquals(490.0, $this->balance($original, $material, $lot));
        // The store the lot moved to never gave anything up, so it is left alone.
        $this->assertEquals(500.0, $this->balance($moved, $material));
    }

    public function test_a_picked_lot_the_location_cannot_cover_is_refused(): void
    {
        $this->blockNegativeStock(true);

        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $lot = MaterialLot::factory()->create([
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-EMPTY',
        ]);

        // The store holds plenty of the material overall, but almost none of this lot.
        $this->stockAt($warehouse, $material, 500);
        $this->stockAt($warehouse, $material, 2, $lot);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $allocation->lotPicks()->create(['material_lot_id' => $lot->id, 'picked_qty' => 100]);

        try {
            $this->service->deduct($allocation->fresh(), 40);
            $this->fail('Consuming a lot the location cannot cover should have been refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('LOT-EMPTY', $e->getMessage());
        }

        // Neither the lot row nor the material total moved.
        $this->assertEquals(2.0, $this->balance($warehouse, $material, $lot));
        $this->assertEquals(500.0, $this->balance($warehouse, $material));
        $this->assertEquals(0.0, (float) $allocation->fresh()->location_deducted_qty);
    }

    public function test_a_correction_credits_each_location_its_own_share_back(): void
    {
        $first = $this->warehouse('WS-A');
        $second = $this->warehouse('WS-B');
        $material = Material::factory()->create();

        $lotA = MaterialLot::factory()->create(['material_id' => $material->id, 'warehouse_id' => $first->id]);
        $lotB = MaterialLot::factory()->create(['material_id' => $material->id, 'warehouse_id' => $second->id]);

        $this->stockAt($first, $material, 500);
        $this->stockAt($second, $material, 500);

        $allocation = $this->allocationOnLine($first, $material);
        $allocation->lotPicks()->create(['material_lot_id' => $lotA->id, 'picked_qty' => 50]);
        $allocation->lotPicks()->create(['material_lot_id' => $lotB->id, 'picked_qty' => 50]);

        $this->service->deduct($allocation->fresh(), 40);
        $this->service->deduct($allocation->fresh(), 10);

        $this->assertEquals(495.0, $this->balance($first, $material));
        $this->assertEquals(495.0, $this->balance($second, $material));
    }

    public function test_cancelling_a_batch_gives_the_location_its_material_back(): void
    {
        $warehouse = $this->warehouse('WS-1');
        $material = Material::factory()->create();
        $this->stockAt($warehouse, $material, 500);

        $allocation = $this->allocationOnLine($warehouse, $material);
        $allocations = app(MaterialAllocationService::class);

        $allocations->recordConsumption($allocation, 60);
        $this->assertEquals(440.0, $this->balance($warehouse, $material));

        $allocations->returnForBatch($allocation->batch);

        $this->assertEquals(500.0, $this->balance($warehouse, $material));
        $this->assertEquals(0.0, (float) $allocation->fresh()->location_deducted_qty);
    }
}
