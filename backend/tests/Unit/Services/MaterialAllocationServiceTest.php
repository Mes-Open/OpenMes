<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\AllocationLotPick;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private MaterialAllocationService $service;

    private User $user;

    private Material $material;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MaterialAllocationService::class);
        $this->user = User::factory()->create();

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'BOLT-M10',
            'name' => 'Bolt M10',
            'material_type_id' => $type->id,
            'unit_of_measure' => 'pcs',
            'stock_quantity' => 1000,
        ]);

        $productType = ProductType::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'product_type_id' => $productType->id,
            'process_snapshot' => [
                'bom' => [[
                    'material_id' => $this->material->id,
                    'material_code' => $this->material->code,
                    'material_name' => $this->material->name,
                    'unit_of_measure' => 'pcs',
                    'quantity_per_unit' => 2.0,
                    'scrap_percentage' => 5.0,
                ]],
            ],
        ]);
        $this->batch = Batch::factory()->create([
            'work_order_id' => $workOrder->id,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);
    }

    public function test_allocate_decrements_stock_and_creates_allocation(): void
    {
        $allocs = $this->service->allocateForBatch($this->batch, $this->user);

        $this->assertCount(1, $allocs);
        // 100 * 2.0 * (1 + 5%) = 210
        $this->assertEqualsWithDelta(210.0, (float) $allocs->first()->allocated_qty, 0.0001);
        $this->assertEqualsWithDelta(790.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $this->assertSame(MaterialAllocation::STATUS_ALLOCATED, $allocs->first()->status);
    }

    public function test_double_allocate_is_idempotent_thanks_to_unique_constraint(): void
    {
        $first = $this->service->allocateForBatch($this->batch, $this->user);
        $second = $this->service->allocateForBatch($this->batch, $this->user);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame($first->first()->id, $second->first()->id);
        // Stock only decremented once.
        $this->assertEqualsWithDelta(790.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
    }

    public function test_return_restores_stock_and_marks_returned(): void
    {
        $this->service->allocateForBatch($this->batch, $this->user);
        $this->assertEqualsWithDelta(790.0, (float) $this->material->fresh()->stock_quantity, 0.0001);

        $this->service->returnForBatch($this->batch);

        $this->assertEqualsWithDelta(1000.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $allocation = MaterialAllocation::firstWhere('batch_id', $this->batch->id);
        $this->assertSame(MaterialAllocation::STATUS_RETURNED, $allocation->status);
        $this->assertEqualsWithDelta(210.0, (float) $allocation->returned_qty, 0.0001);
    }

    public function test_consume_marks_consumed_without_touching_stock(): void
    {
        $this->service->allocateForBatch($this->batch, $this->user);
        $this->service->consumeForBatch($this->batch);

        $allocation = MaterialAllocation::firstWhere('batch_id', $this->batch->id);
        $this->assertSame(MaterialAllocation::STATUS_CONSUMED, $allocation->status);
        $this->assertNotNull($allocation->consumed_at);
        // Stock already decremented at allocate time; consume must not double-charge.
        $this->assertEqualsWithDelta(790.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
    }

    public function test_resolves_material_by_id_even_when_code_changes(): void
    {
        // Snapshot was taken with the original code. Now admin renames the material.
        $this->material->update(['code' => 'BOLT-M10-V2']);

        $allocs = $this->service->allocateForBatch($this->batch, $this->user);

        $this->assertCount(1, $allocs);
        $this->assertSame($this->material->id, $allocs->first()->material_id);
    }

    public function test_falls_back_to_code_lookup_when_snapshot_has_no_material_id(): void
    {
        // Older snapshots may not carry material_id (legacy).
        $wo = $this->batch->workOrder;
        $snap = $wo->process_snapshot;
        unset($snap['bom'][0]['material_id']);
        $wo->update(['process_snapshot' => $snap]);

        $allocs = $this->service->allocateForBatch($this->batch, $this->user);

        $this->assertCount(1, $allocs);
        $this->assertSame($this->material->id, $allocs->first()->material_id);
    }

    public function test_block_negative_stock_throws_when_required_exceeds_stock(): void
    {
        DB::table('system_settings')
            ->updateOrInsert(['key' => 'block_negative_stock'], ['value' => json_encode(true)]);

        $this->material->update(['stock_quantity' => 50]); // need 210

        $this->expectException(InsufficientStockException::class);
        $this->service->allocateForBatch($this->batch, $this->user);

        // Stock untouched, no allocation created.
        $this->assertEqualsWithDelta(50.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $this->assertSame(0, MaterialAllocation::count());
    }

    public function test_block_negative_stock_off_allows_negative(): void
    {
        DB::table('system_settings')
            ->updateOrInsert(['key' => 'block_negative_stock'], ['value' => json_encode(false)]);

        $this->material->update(['stock_quantity' => 50]); // need 210

        $allocs = $this->service->allocateForBatch($this->batch, $this->user);

        $this->assertCount(1, $allocs);
        $this->assertEqualsWithDelta(-160.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
    }

    public function test_preview_returns_planned_required_and_availability(): void
    {
        $preview = $this->service->previewForBatch($this->batch);

        $this->assertCount(1, $preview);
        $this->assertEqualsWithDelta(210.0, $preview[0]['required_qty'], 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $preview[0]['available_qty'], 0.0001);
        $this->assertTrue($preview[0]['sufficient']);
    }

    // ── WO-time lot picking (suggest + override) ────────────────────────────

    private function enableLotTracking(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'lot_tracking_enabled'], ['value' => json_encode(true)]);
    }

    private function makeLot(string $number, float $qty): MaterialLot
    {
        return MaterialLot::create([
            'material_id' => $this->material->id,
            'lot_number' => $number,
            'unit_of_measure' => 'pcs',
            'quantity_received' => $qty,
            'quantity_available' => $qty,
            'received_at' => now(),
            'status' => MaterialLot::STATUS_RELEASED,
        ]);
    }

    private function makeStep(int $number = 1): BatchStep
    {
        return BatchStep::create([
            'batch_id' => $this->batch->id,
            'step_number' => $number,
            'name' => 'Step '.$number,
            'status' => BatchStep::STATUS_PENDING,
        ]);
    }

    public function test_allocate_with_manual_picks_uses_chosen_lots(): void
    {
        $this->enableLotTracking();
        $step = $this->makeStep();
        $lotA = $this->makeLot('LOT-A', 150);
        $lotB = $this->makeLot('LOT-B', 100); // required = 210

        $allocs = $this->service->allocateForBatch($this->batch, $this->user, [
            $this->material->id => [
                ['material_lot_id' => $lotA->id, 'picked_qty' => 150],
                ['material_lot_id' => $lotB->id, 'picked_qty' => 60],
            ],
        ], attributeStepId: $step->id);

        $this->assertSame(2, AllocationLotPick::count());
        $this->assertEqualsWithDelta(0.0, (float) $lotA->fresh()->quantity_available, 0.0001);
        $this->assertEqualsWithDelta(40.0, (float) $lotB->fresh()->quantity_available, 0.0001);
        AllocationLotPick::all()->each(fn ($p) => $this->assertSame(AllocationLotPick::STRATEGY_MANUAL, $p->picking_strategy));
        $this->assertSame($step->id, $allocs->first()->batch_step_id);
    }

    public function test_allocate_without_picks_falls_back_to_auto_picking(): void
    {
        $this->enableLotTracking();
        $this->makeLot('LOT-A', 300);

        $this->service->allocateForBatch($this->batch, $this->user);

        $this->assertSame(1, AllocationLotPick::count());
        $this->assertSame(AllocationLotPick::STRATEGY_FEFO, AllocationLotPick::first()->picking_strategy);
    }

    public function test_consume_writes_genealogy_from_picks_and_is_idempotent(): void
    {
        $this->enableLotTracking();
        $step = $this->makeStep();
        $lot = $this->makeLot('LOT-A', 300);

        $this->service->allocateForBatch($this->batch, $this->user, [], attributeStepId: $step->id);
        $this->service->consumeForBatch($this->batch);

        $this->assertSame(1, BatchStepLotConsumption::count());
        $row = BatchStepLotConsumption::first();
        $this->assertSame($step->id, $row->batch_step_id);
        $this->assertSame($lot->id, $row->material_lot_id);
        $this->assertEqualsWithDelta(210.0, (float) $row->quantity_consumed, 0.0001);

        // Allocations already CONSUMED → a second pass writes nothing more.
        $this->service->consumeForBatch($this->batch);
        $this->assertSame(1, BatchStepLotConsumption::count());
    }

    public function test_pick_preview_for_step_returns_proposal_when_tracking_on(): void
    {
        $this->enableLotTracking();
        $step = $this->makeStep();
        $this->makeLot('LOT-A', 300);

        $preview = $this->service->pickPreviewForStep($step);

        $this->assertCount(1, $preview);
        $this->assertSame($this->material->id, $preview[0]['material_id']);
        $this->assertEqualsWithDelta(210.0, $preview[0]['required_qty'], 0.0001);
        $this->assertCount(1, $preview[0]['proposed']);
        $this->assertCount(1, $preview[0]['candidates']);
    }

    public function test_pick_preview_for_step_empty_when_tracking_off(): void
    {
        $step = $this->makeStep();
        $this->makeLot('LOT-A', 300);

        $this->assertSame([], $this->service->pickPreviewForStep($step));
    }

    public function test_pick_preview_for_step_skips_serial_tracked_material(): void
    {
        $this->enableLotTracking();
        $this->material->update(['tracking_type' => 'serial']);
        $step = $this->makeStep();
        $this->makeLot('LOT-A', 300);

        $this->assertSame([], $this->service->pickPreviewForStep($step));
    }
}
