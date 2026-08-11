<?php

namespace Tests\Unit;

use App\Enums\ChangeEffectivePoint;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Configuration versioning (#182).
 */
class WorkOrderSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkOrderSnapshotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkOrderSnapshotService::class);
    }

    public function test_baseline_captures_the_configuration_the_order_was_released_with(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $baseline = $this->service->ensureBaseline($workOrder);

        $this->assertSame(1, $baseline->version);
        $this->assertSame($workOrder->process_snapshot, $baseline->snapshot);
    }

    public function test_baseline_is_idempotent(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $first = $this->service->ensureBaseline($workOrder);
        $second = $this->service->ensureBaseline($workOrder);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $workOrder->snapshots()->count());
    }

    public function test_each_version_is_appended_and_the_previous_one_is_left_untouched(): void
    {
        $workOrder = WorkOrder::factory()->create(['process_snapshot' => ['steps' => [['step_number' => 1, 'name' => 'Original']]]]);

        $this->service->ensureBaseline($workOrder);
        $v2 = $this->service->createVersion(
            $workOrder,
            ['steps' => [['step_number' => 1, 'name' => 'Changed']]],
            ChangeEffectivePoint::NextBatch,
        );

        $this->assertSame(2, $v2->version);
        $this->assertSame(2, $workOrder->fresh()->snapshot_version);

        // Version 1 still says what the shop floor was originally told to build.
        $v1 = $workOrder->snapshots()->where('version', 1)->firstOrFail();
        $this->assertSame('Original', $v1->snapshot['steps'][0]['name']);

        // The order now runs on the new configuration.
        $this->assertSame('Changed', $workOrder->fresh()->process_snapshot['steps'][0]['name']);
    }

    public function test_remaining_quantity_version_records_the_unit_boundary(): void
    {
        $workOrder = WorkOrder::factory()->create(['planned_qty' => 100, 'produced_qty' => 35]);

        $version = $this->service->createVersion($workOrder, [], ChangeEffectivePoint::RemainingQuantity);

        // Units 1–35 ran under v1; 36 onwards run under v2.
        $this->assertEquals(35.0, (float) $version->effective_from_qty);
    }

    public function test_next_batch_version_records_no_unit_boundary(): void
    {
        $workOrder = WorkOrder::factory()->create(['planned_qty' => 100, 'produced_qty' => 35]);

        $version = $this->service->createVersion($workOrder, [], ChangeEffectivePoint::NextBatch);

        // Attribution runs through batches.snapshot_version instead.
        $this->assertNull($version->effective_from_qty);
    }

    public function test_remaining_requirements_cover_only_what_is_left_to_produce(): void
    {
        $workOrder = WorkOrder::factory()->create(['planned_qty' => 100, 'produced_qty' => 40]);

        $requirements = $this->service->remainingRequirements($workOrder, [
            'bom' => [
                ['material_id' => 7, 'quantity_per_unit' => 2, 'scrap_percentage' => 0],
                ['material_id' => 9, 'quantity_per_unit' => 1, 'scrap_percentage' => 10],
            ],
        ]);

        // 60 remaining × 2 per unit.
        $this->assertEquals(120.0, $requirements[0]['remaining_qty']);
        // 60 × 1 × 1.10 — scrap allowance included.
        $this->assertEquals(66.0, $requirements[1]['remaining_qty']);
    }

    public function test_a_finished_order_needs_nothing_more(): void
    {
        $workOrder = WorkOrder::factory()->create(['planned_qty' => 100, 'produced_qty' => 100]);

        $requirements = $this->service->remainingRequirements($workOrder, [
            'bom' => [['material_id' => 7, 'quantity_per_unit' => 2]],
        ]);

        $this->assertEquals(0.0, $requirements[0]['remaining_qty']);
    }
}
