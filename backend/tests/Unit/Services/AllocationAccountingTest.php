<?php

namespace Tests\Unit\Services;

use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationAccountingTest extends TestCase
{
    use RefreshDatabase;

    private MaterialAllocationService $svc;

    private User $user;

    private Material $material;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(MaterialAllocationService::class);
        $this->user = User::factory()->create();

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'M', 'name' => 'M',
            'material_type_id' => $type->id,
            'unit_of_measure' => 'kg',
            'stock_quantity' => 500,
        ]);

        $productType = ProductType::factory()->create();
        $wo = WorkOrder::factory()->create([
            'product_type_id' => $productType->id,
            'process_snapshot' => [
                'bom' => [[
                    'material_id' => $this->material->id,
                    'material_code' => $this->material->code,
                    'unit_of_measure' => 'kg',
                    'quantity_per_unit' => 1.0,
                    'scrap_percentage' => 0,
                ]],
            ],
        ]);
        $this->batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);
    }

    public function test_allocate_decrements_stock_and_bumps_reserved(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);

        $m = $this->material->fresh();
        $this->assertEqualsWithDelta(400.0, (float) $m->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $m->reserved_quantity, 0.0001);
        $this->assertEqualsWithDelta(300.0, $m->available_quantity, 0.0001);
    }

    public function test_preview_returns_available_not_raw_stock(): void
    {
        // Simulate another batch reserving 200 kg.
        $this->material->update(['reserved_quantity' => 200]);

        $preview = $this->svc->previewForBatch($this->batch);

        $this->assertEqualsWithDelta(500.0, $preview[0]['on_hand_qty'], 0.0001);
        $this->assertEqualsWithDelta(200.0, $preview[0]['reserved_qty'], 0.0001);
        $this->assertEqualsWithDelta(300.0, (float) $preview[0]['available_qty'], 0.0001);
        $this->assertTrue($preview[0]['sufficient']); // need 100, have 300 available
    }

    public function test_record_consumption_marks_actual_and_scrap_without_changing_status(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);
        $allocation = MaterialAllocation::first();

        $this->svc->recordConsumption($allocation, actualConsumed: 95.0, scrap: 3.0);

        $fresh = $allocation->fresh();
        $this->assertEqualsWithDelta(95.0, (float) $fresh->consumed_qty, 0.0001);
        $this->assertEqualsWithDelta(3.0, (float) $fresh->scrap_qty, 0.0001);
        $this->assertSame(MaterialAllocation::STATUS_ALLOCATED, $fresh->status);
    }

    public function test_consume_for_batch_returns_leftover_to_stock(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);
        $allocation = MaterialAllocation::first();

        // Operator says we only used 90 and 5 was scrap → 5 should return.
        $this->svc->recordConsumption($allocation, actualConsumed: 90.0, scrap: 5.0);

        $this->svc->consumeForBatch($this->batch);

        $m = $this->material->fresh();
        // Started 500, allocated -100 = 400, returned +5 leftover = 405.
        $this->assertEqualsWithDelta(405.0, (float) $m->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $m->reserved_quantity, 0.0001);

        $a = $allocation->fresh();
        $this->assertSame(MaterialAllocation::STATUS_CONSUMED, $a->status);
        $this->assertNotNull($a->consumed_at);
    }

    public function test_consume_for_batch_with_no_record_assumes_planned(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);
        $this->svc->consumeForBatch($this->batch);

        $m = $this->material->fresh();
        // No leftover return; reserved released.
        $this->assertEqualsWithDelta(400.0, (float) $m->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $m->reserved_quantity, 0.0001);
    }

    public function test_adjust_allocation_changes_allocated_and_stock(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);
        $allocation = MaterialAllocation::first();

        // Operator adds 10kg extra mid-batch.
        $this->svc->adjustAllocation($allocation, 10.0, $this->user, reason: 'Extra dose');

        $fresh = $allocation->fresh();
        $this->assertEqualsWithDelta(110.0, (float) $fresh->allocated_qty, 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $fresh->expected_qty, 0.0001); // immutable
        $this->assertEqualsWithDelta(10.0, (float) $fresh->adjustment_qty, 0.0001);

        $m = $this->material->fresh();
        $this->assertEqualsWithDelta(390.0, (float) $m->stock_quantity, 0.0001); // 500-100-10
        $this->assertEqualsWithDelta(110.0, (float) $m->reserved_quantity, 0.0001);
    }

    public function test_adjust_allocation_can_be_negative(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);
        $allocation = MaterialAllocation::first();

        $this->svc->adjustAllocation($allocation, -20.0, $this->user, reason: 'Less needed');

        $m = $this->material->fresh();
        $this->assertEqualsWithDelta(420.0, (float) $m->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(80.0, (float) $m->reserved_quantity, 0.0001);
    }

    public function test_return_releases_reservation(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);
        $this->assertEqualsWithDelta(100.0, (float) $this->material->fresh()->reserved_quantity, 0.0001);

        $this->svc->returnForBatch($this->batch);

        $m = $this->material->fresh();
        $this->assertEqualsWithDelta(500.0, (float) $m->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $m->reserved_quantity, 0.0001);
    }

    public function test_variance_attribute_reflects_consumed_minus_expected(): void
    {
        $this->svc->allocateForBatch($this->batch, $this->user);
        $allocation = MaterialAllocation::first();

        $this->svc->recordConsumption($allocation, actualConsumed: 105.0);

        $this->assertEqualsWithDelta(5.0, $allocation->fresh()->variance_qty, 0.0001);
    }
}
