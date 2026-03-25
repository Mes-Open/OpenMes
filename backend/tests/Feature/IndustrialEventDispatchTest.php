<?php

namespace Tests\Feature;

use App\Events\WorkOrder\WorkOrderCreated;
use App\Events\WorkOrder\WorkOrderCompleted;
use App\Events\Batch\BatchCreated;
use App\Events\Batch\BatchCompleted;
use App\Events\BatchStep\StepStarted;
use App\Events\BatchStep\StepCompleted;
use App\Models\WorkOrder;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\ProductType;
use App\Models\Line;
use App\Models\User;
use App\Services\WorkOrder\WorkOrderService;
use App\Services\WorkOrder\BatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class IndustrialEventDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected WorkOrderService $workOrderService;
    protected BatchService $batchService;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workOrderService = app(WorkOrderService::class);
        $this->batchService = app(BatchService::class);
        $this->admin = User::factory()->create();
    }

    public function test_work_order_creation_dispatches_event(): void
    {
        Event::fake();

        $this->workOrderService->createWorkOrder([
            'order_no' => 'WO-TEST-001',
            'planned_qty' => 100,
        ]);

        Event::assertDispatched(WorkOrderCreated::class);
    }

    public function test_batch_creation_dispatches_event(): void
    {
        Event::fake();

        $wo = WorkOrder::factory()->create();
        $this->workOrderService->createBatch($wo, 50);

        Event::assertDispatched(BatchCreated::class);
    }

    public function test_batch_step_lifecycle_dispatches_events(): void
    {
        Event::fake();

        $batch = Batch::factory()->create();
        $step = BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1]);

        // Start Step
        $this->batchService->startStep($step, $this->admin);
        Event::assertDispatched(StepStarted::class);

        // Complete Step
        $this->batchService->completeStep($step, $this->admin);
        Event::assertDispatched(StepCompleted::class);
    }

    public function test_work_order_completion_dispatches_event(): void
    {
        Event::fake();

        $wo = WorkOrder::factory()->create(['planned_qty' => 10, 'produced_qty' => 0]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'target_qty' => 10]);
        $step = BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1]);

        // Complete the step which should complete the batch and then the work order
        $this->batchService->startStep($step, $this->admin);
        $this->batchService->completeStep($step, $this->admin, ['produced_qty' => 10]);

        Event::assertDispatched(BatchCompleted::class);
        Event::assertDispatched(WorkOrderCompleted::class);
    }
}
