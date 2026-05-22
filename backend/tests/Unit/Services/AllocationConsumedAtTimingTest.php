<?php

namespace Tests\Unit\Services;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that BOM rows with consumed_at = start | during | end are
 * allocated at the right moment, and that stock_movements records every
 * transition.
 */
class AllocationConsumedAtTimingTest extends TestCase
{
    use RefreshDatabase;

    private MaterialAllocationService $svc;

    private User $user;

    private Material $matStart;

    private Material $matDuring;

    private Material $matEnd;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(MaterialAllocationService::class);
        $this->user = User::factory()->create();

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->matStart = $this->makeMaterial($type->id, 'START-MAT', 1000);
        $this->matDuring = $this->makeMaterial($type->id, 'DURING-MAT', 1000);
        $this->matEnd = $this->makeMaterial($type->id, 'END-MAT', 1000);

        $productType = ProductType::factory()->create();
        $wo = WorkOrder::factory()->create([
            'product_type_id' => $productType->id,
            'process_snapshot' => [
                'bom' => [
                    [
                        'material_id' => $this->matStart->id,
                        'material_code' => 'START-MAT',
                        'unit_of_measure' => 'kg',
                        'quantity_per_unit' => 1.0,
                        'scrap_percentage' => 0,
                        'consumed_at' => 'start',
                    ],
                    [
                        'material_id' => $this->matDuring->id,
                        'material_code' => 'DURING-MAT',
                        'unit_of_measure' => 'kg',
                        'quantity_per_unit' => 0.5,
                        'scrap_percentage' => 0,
                        'consumed_at' => 'during',
                        'step_number' => 2,
                    ],
                    [
                        'material_id' => $this->matEnd->id,
                        'material_code' => 'END-MAT',
                        'unit_of_measure' => 'kg',
                        'quantity_per_unit' => 2.0,
                        'scrap_percentage' => 0,
                        'consumed_at' => 'end',
                    ],
                ],
            ],
        ]);
        $this->batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);
    }

    private function makeMaterial(int $typeId, string $code, float $stock): Material
    {
        return Material::create([
            'code' => $code,
            'name' => $code,
            'material_type_id' => $typeId,
            'unit_of_measure' => 'kg',
            'stock_quantity' => $stock,
        ]);
    }

    public function test_allocate_for_batch_only_picks_start_items(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);

        $this->assertSame(1, MaterialAllocation::count());
        $this->assertSame($this->matStart->id, MaterialAllocation::first()->material_id);
        $this->assertEqualsWithDelta(900.0, (float) $this->matStart->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $this->matDuring->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $this->matEnd->fresh()->stock_quantity, 0.0001);
    }

    public function test_allocate_for_step_picks_during_items_matching_step_number(): void
    {
        $step = BatchStep::create([
            'batch_id' => $this->batch->id,
            'step_number' => 2,
            'name' => 'Mix',
            'status' => BatchStep::STATUS_PENDING,
        ]);

        $allocs = $this->svc->allocateForStep($step, $this->user);

        $this->assertCount(1, $allocs);
        $this->assertSame($this->matDuring->id, $allocs->first()->material_id);
        $this->assertSame($step->id, $allocs->first()->batch_step_id);
        $this->assertEqualsWithDelta(950.0, (float) $this->matDuring->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $this->matStart->fresh()->stock_quantity, 0.0001);
    }

    public function test_allocate_for_step_skips_when_no_matching_step_number(): void
    {
        $step = BatchStep::create([
            'batch_id' => $this->batch->id,
            'step_number' => 99,
            'name' => 'Unrelated',
            'status' => BatchStep::STATUS_PENDING,
        ]);

        $allocs = $this->svc->allocateForStep($step, $this->user);

        $this->assertCount(0, $allocs);
    }

    public function test_allocate_for_batch_end_picks_only_end_items(): void
    {
        $allocs = $this->svc->allocateForBatchEnd($this->batch, $this->user);

        $this->assertCount(1, $allocs);
        $this->assertSame($this->matEnd->id, $allocs->first()->material_id);
        $this->assertEqualsWithDelta(800.0, (float) $this->matEnd->fresh()->stock_quantity, 0.0001);
    }

    public function test_each_allocation_creates_a_stock_movement(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);

        $mv = StockMovement::where('material_id', $this->matStart->id)->first();
        $this->assertNotNull($mv);
        $this->assertSame(StockMovement::TYPE_ALLOCATION, $mv->movement_type);
        $this->assertEqualsWithDelta(-100.0, (float) $mv->quantity, 0.0001);
        $this->assertEqualsWithDelta(900.0, (float) $mv->balance_after, 0.0001);
        $this->assertSame(StockMovement::SOURCE_BATCH, $mv->source_type);
        $this->assertSame($this->batch->id, $mv->source_id);
    }

    public function test_return_creates_a_stock_movement(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);
        $this->svc->returnForBatch($this->batch);

        $this->assertSame(2, StockMovement::where('material_id', $this->matStart->id)->count());
        $latest = StockMovement::where('material_id', $this->matStart->id)
            ->orderByDesc('id')->first();
        $this->assertSame(StockMovement::TYPE_RETURN, $latest->movement_type);
        $this->assertEqualsWithDelta(100.0, (float) $latest->quantity, 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $latest->balance_after, 0.0001);
    }
}
