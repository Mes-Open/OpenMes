<?php

namespace Tests\Unit\Services;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BatchService $service;
    protected WorkOrder $workOrder;
    protected Batch $batch;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BatchService::class);

        $this->workOrder = WorkOrder::factory()->create();
        $workOrderService = app(WorkOrderService::class);
        $this->batch = $workOrderService->createBatch($this->workOrder, 50);
        $this->user = User::factory()->create();
    }

    // ── startStep() ──────────────────────────────────────────────────────────

    public function test_start_step_changes_status_to_in_progress(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();

        $this->service->startStep($step, $this->user);

        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $step->fresh()->status);
    }

    public function test_start_step_sets_started_at_and_user(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();

        $this->service->startStep($step, $this->user);

        $fresh = $step->fresh();
        $this->assertNotNull($fresh->started_at);
        $this->assertEquals($this->user->id, $fresh->started_by_id);
    }

    public function test_start_step_updates_batch_to_in_progress(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();
        $this->assertEquals(Batch::STATUS_PENDING, $this->batch->status);

        $this->service->startStep($step, $this->user);

        $this->assertEquals(Batch::STATUS_IN_PROGRESS, $this->batch->fresh()->status);
    }

    public function test_start_step_updates_work_order_to_in_progress(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();

        $this->service->startStep($step, $this->user);

        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
    }

    public function test_start_already_in_progress_step_throws(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();
        $step->update(['status' => BatchStep::STATUS_IN_PROGRESS]);

        $this->expectException(\Exception::class);

        $this->service->startStep($step, $this->user);
    }

    public function test_start_second_step_before_first_complete_throws(): void
    {
        $secondStep = $this->batch->steps()->where('step_number', 2)->first();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/must be completed before/i');

        $this->service->startStep($secondStep, $this->user);
    }

    public function test_start_second_step_after_first_complete_succeeds(): void
    {
        $firstStep = $this->batch->steps()->where('step_number', 1)->first();
        $firstStep->update([
            'status'       => BatchStep::STATUS_DONE,
            'started_at'   => now()->subMinutes(30),
            'completed_at' => now(),
        ]);

        $secondStep = $this->batch->steps()->where('step_number', 2)->first();

        $this->service->startStep($secondStep, $this->user);

        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $secondStep->fresh()->status);
    }

    // ── completeStep() ───────────────────────────────────────────────────────

    public function test_complete_in_progress_step_changes_status_to_done(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();
        $step->update([
            'status'     => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(15),
        ]);

        $this->service->completeStep($step, $this->user);

        $this->assertEquals(BatchStep::STATUS_DONE, $step->fresh()->status);
    }

    public function test_complete_step_sets_completed_at_and_user(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();
        $step->update([
            'status'     => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(10),
        ]);

        $this->service->completeStep($step, $this->user);

        $fresh = $step->fresh();
        $this->assertNotNull($fresh->completed_at);
        $this->assertEquals($this->user->id, $fresh->completed_by_id);
    }

    public function test_complete_step_calculates_duration(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();
        $step->update([
            'status'     => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(30),
        ]);

        $this->service->completeStep($step, $this->user);

        $fresh = $step->fresh();
        $this->assertNotNull($fresh->duration_minutes);
        $this->assertGreaterThanOrEqual(29, $fresh->duration_minutes);
        $this->assertLessThanOrEqual(31, $fresh->duration_minutes);
    }

    public function test_complete_pending_step_throws(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();
        $this->assertEquals(BatchStep::STATUS_PENDING, $step->status);

        $this->expectException(\Exception::class);

        $this->service->completeStep($step, $this->user);
    }

    public function test_completing_all_steps_marks_batch_done(): void
    {
        $steps = $this->batch->steps()->orderBy('step_number')->get();

        // Complete all but last manually
        foreach ($steps->slice(0, -1) as $step) {
            $step->update([
                'status'       => BatchStep::STATUS_DONE,
                'started_at'   => now()->subHour(),
                'completed_at' => now()->subMinutes(30),
            ]);
        }

        // Complete last step through service
        $lastStep = $steps->last();
        $lastStep->update([
            'status'     => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(10),
        ]);

        $this->service->completeStep($lastStep, $this->user, ['produced_qty' => 50]);

        $this->assertEquals(Batch::STATUS_DONE, $this->batch->fresh()->status);
        $this->assertNotNull($this->batch->fresh()->completed_at);
    }

    public function test_completing_all_steps_updates_work_order_produced_qty(): void
    {
        $steps = $this->batch->steps()->orderBy('step_number')->get();

        foreach ($steps->slice(0, -1) as $step) {
            $step->update([
                'status'       => BatchStep::STATUS_DONE,
                'started_at'   => now()->subHour(),
                'completed_at' => now()->subMinutes(30),
            ]);
        }

        $lastStep = $steps->last();
        $lastStep->update([
            'status'     => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->service->completeStep($lastStep, $this->user, ['produced_qty' => 42]);

        $this->assertEquals(42, $this->workOrder->fresh()->produced_qty);
    }

    public function test_completing_all_steps_marks_work_order_done_when_fully_produced(): void
    {
        $this->workOrder->update(['planned_qty' => 50]);
        $steps = $this->batch->steps()->orderBy('step_number')->get();

        foreach ($steps->slice(0, -1) as $step) {
            $step->update([
                'status'       => BatchStep::STATUS_DONE,
                'started_at'   => now()->subHour(),
                'completed_at' => now()->subMinutes(30),
            ]);
        }

        $lastStep = $steps->last();
        $lastStep->update([
            'status'     => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->service->completeStep($lastStep, $this->user, ['produced_qty' => 50]);

        $this->assertEquals(WorkOrder::STATUS_DONE, $this->workOrder->fresh()->status);
    }
}
