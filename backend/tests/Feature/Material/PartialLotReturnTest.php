<?php

namespace Tests\Feature\Material;

use App\Models\AllocationLotPick;
use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Services\Material\LotPickingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Partial lot restoration when unused material is returned (#99). Picks are walked
 * newest-first: the returned quantity restores each lot's available quantity,
 * reopens a depleted lot, and reduces or deletes the pick rows.
 */
class PartialLotReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_return_restores_lots_newest_first_and_reopens_them(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(true)],
        );

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $material = Material::create([
            'code' => 'M1', 'name' => 'Material 1', 'material_type_id' => $type->id,
            'unit_of_measure' => 'kg', 'tracking_type' => 'batch', 'stock_quantity' => 0,
        ]);

        // Two lots fully drawn down by the allocation (available 0, CONSUMED).
        $lotA = MaterialLot::factory()->create([
            'material_id' => $material->id, 'lot_number' => 'A',
            'quantity_received' => 40, 'quantity_available' => 0, 'status' => MaterialLot::STATUS_CONSUMED,
        ]);
        $lotB = MaterialLot::factory()->create([
            'material_id' => $material->id, 'lot_number' => 'B',
            'quantity_received' => 60, 'quantity_available' => 0, 'status' => MaterialLot::STATUS_CONSUMED,
        ]);

        $wo = WorkOrder::factory()->create(['product_type_id' => ProductType::factory()->create()->id]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'target_qty' => 100, 'status' => Batch::STATUS_IN_PROGRESS]);
        $allocation = MaterialAllocation::create([
            'batch_id' => $batch->id, 'work_order_id' => $wo->id, 'material_id' => $material->id,
            'allocated_qty' => 100, 'status' => MaterialAllocation::STATUS_ALLOCATED,
            'allocated_at' => now(),
        ]);
        // Pick A first (id lower), then B (id higher) → reverse walk hits B first.
        $pickA = AllocationLotPick::create(['material_allocation_id' => $allocation->id, 'material_lot_id' => $lotA->id, 'picked_qty' => 40, 'picking_strategy' => 'fifo']);
        $pickB = AllocationLotPick::create(['material_allocation_id' => $allocation->id, 'material_lot_id' => $lotB->id, 'picked_qty' => 60, 'picking_strategy' => 'fifo']);

        app(LotPickingService::class)->returnPartialForAllocation($allocation, 70);

        // 70 returned newest-first: B gets its full 60 back (pick deleted, reopened),
        // then A gets the remaining 10 (pick reduced to 30, reopened).
        $lotB->refresh();
        $this->assertEqualsWithDelta(60.0, (float) $lotB->quantity_available, 0.0001);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lotB->status);
        $this->assertNull(AllocationLotPick::find($pickB->id));

        $lotA->refresh();
        $this->assertEqualsWithDelta(10.0, (float) $lotA->quantity_available, 0.0001);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lotA->status);
        $this->assertEqualsWithDelta(30.0, (float) AllocationLotPick::find($pickA->id)->picked_qty, 0.0001);
    }
}
