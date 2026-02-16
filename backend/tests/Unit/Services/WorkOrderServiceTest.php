<?php

namespace Tests\Unit\Services;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\ProcessTemplate;
use App\Models\WorkOrder;
use App\Models\Batch;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WorkOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkOrderService::class);
    }

    public function test_create_work_order_generates_process_snapshot(): void
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        $processTemplate = ProcessTemplate::factory()
            ->withSteps(3)
            ->create(['product_type_id' => $productType->id]);

        $workOrder = $this->service->createWorkOrder([
            'order_no' => 'WO-TEST-001',
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
        ]);

        $this->assertInstanceOf(WorkOrder::class, $workOrder);
        $this->assertNotNull($workOrder->process_snapshot);
        $this->assertIsArray($workOrder->process_snapshot);
        $this->assertArrayHasKey('steps', $workOrder->process_snapshot);
        $this->assertCount(3, $workOrder->process_snapshot['steps']);
    }

    public function test_create_work_order_uses_active_process_template(): void
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        // Create version 1 (inactive)
        $oldTemplate = ProcessTemplate::factory()
            ->inactive()
            ->withSteps(2)
            ->create([
                'product_type_id' => $productType->id,
                'version' => 1,
            ]);

        // Create version 2 (active)
        $activeTemplate = ProcessTemplate::factory()
            ->withSteps(4)
            ->create([
                'product_type_id' => $productType->id,
                'version' => 2,
            ]);

        $workOrder = $this->service->createWorkOrder([
            'order_no' => 'WO-TEST-002',
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
        ]);

        // Should use version 2 with 4 steps
        $this->assertCount(4, $workOrder->process_snapshot['steps']);
        $this->assertEquals(2, $workOrder->process_snapshot['template_version']);
    }

    public function test_create_batch_initializes_steps_from_snapshot(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $this->assertCount(3, $workOrder->process_snapshot['steps']); // From factory

        $batch = $this->service->createBatch($workOrder, 50);

        $this->assertInstanceOf(Batch::class, $batch);
        $this->assertEquals(50, $batch->target_qty);
        $this->assertEquals(0, $batch->produced_qty);
        $this->assertEquals(Batch::STATUS_PENDING, $batch->status);

        // Verify steps were created from snapshot
        $this->assertCount(3, $batch->steps);

        foreach ($batch->steps as $index => $step) {
            $snapshotStep = $workOrder->process_snapshot['steps'][$index];

            $this->assertEquals($snapshotStep['step_number'], $step->step_number);
            $this->assertEquals($snapshotStep['name'], $step->name);
            $this->assertEquals($snapshotStep['instruction'], $step->instruction);
            $this->assertEquals('PENDING', $step->status);
        }
    }

    public function test_create_batch_auto_increments_batch_number(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $batch1 = $this->service->createBatch($workOrder, 30);
        $batch2 = $this->service->createBatch($workOrder, 40);
        $batch3 = $this->service->createBatch($workOrder, 30);

        $this->assertEquals(1, $batch1->batch_number);
        $this->assertEquals(2, $batch2->batch_number);
        $this->assertEquals(3, $batch3->batch_number);
    }

    public function test_update_work_order_status_sets_blocked_when_blocking_issues_exist(): void
    {
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);

        $workOrder = WorkOrder::factory()->create();
        $this->assertEquals(WorkOrder::STATUS_PENDING, $workOrder->status);

        // Create a blocking issue
        $issueType = \App\Models\IssueType::where('is_blocking', true)->first();
        \App\Models\Issue::factory()->create([
            'work_order_id' => $workOrder->id,
            'issue_type_id' => $issueType->id,
            'status' => 'OPEN',
        ]);

        $this->service->updateWorkOrderStatus($workOrder);

        $workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_BLOCKED, $workOrder->status);
    }

    public function test_update_work_order_status_sets_in_progress_when_batch_active(): void
    {
        $workOrder = WorkOrder::factory()->create();

        Batch::factory()->create([
            'work_order_id' => $workOrder->id,
            'status' => Batch::STATUS_IN_PROGRESS,
        ]);

        $this->service->updateWorkOrderStatus($workOrder);

        $workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $workOrder->status);
    }

    public function test_update_work_order_status_sets_done_when_complete(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'planned_qty' => 100,
            'produced_qty' => 0,
        ]);

        // Simulate batch completion
        $workOrder->update(['produced_qty' => 100]);

        $this->service->updateWorkOrderStatus($workOrder);

        $workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_DONE, $workOrder->status);
        $this->assertNotNull($workOrder->completed_at);
    }

    public function test_cannot_update_completed_work_order(): void
    {
        $workOrder = WorkOrder::factory()->done()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot update completed work order');

        $this->service->updateWorkOrder($workOrder, [
            'planned_qty' => 200,
        ]);
    }

    public function test_get_work_orders_for_user_filters_by_assigned_lines(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $user = \App\Models\User::factory()->operator()->create();
        $line1 = Line::factory()->create();
        $line2 = Line::factory()->create();

        // Assign user to line1 only
        $user->lines()->attach($line1->id);

        $workOrder1 = WorkOrder::factory()->create(['line_id' => $line1->id]);
        $workOrder2 = WorkOrder::factory()->create(['line_id' => $line2->id]);

        $result = $this->service->getWorkOrdersForUser($user);

        $this->assertCount(1, $result);
        $this->assertEquals($workOrder1->id, $result->first()->id);
    }

    public function test_get_work_orders_for_user_applies_status_filter(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $user = \App\Models\User::factory()->admin()->create();
        $line = Line::factory()->create();

        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING
        ]);
        WorkOrder::factory()->inProgress()->create(['line_id' => $line->id]);
        WorkOrder::factory()->done()->create(['line_id' => $line->id]);

        $result = $this->service->getWorkOrdersForUser($user, [
            'status' => WorkOrder::STATUS_PENDING
        ]);

        $this->assertCount(1, $result);
        $this->assertEquals(WorkOrder::STATUS_PENDING, $result->first()->status);
    }
}
