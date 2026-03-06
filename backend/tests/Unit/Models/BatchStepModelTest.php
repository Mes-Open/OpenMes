<?php

namespace Tests\Unit\Models;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\IssueType;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchStepModelTest extends TestCase
{
    use RefreshDatabase;

    protected WorkOrder $workOrder;
    protected Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workOrder = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);
        $this->batch = app(WorkOrderService::class)->createBatch($this->workOrder, 50);
    }

    // ── Status constants ─────────────────────────────────────────────────────

    public function test_status_constants_defined(): void
    {
        $this->assertEquals('PENDING', BatchStep::STATUS_PENDING);
        $this->assertEquals('IN_PROGRESS', BatchStep::STATUS_IN_PROGRESS);
        $this->assertEquals('DONE', BatchStep::STATUS_DONE);
        $this->assertEquals('SKIPPED', BatchStep::STATUS_SKIPPED);
    }

    // ── canStart() ───────────────────────────────────────────────────────────

    public function test_can_start_returns_true_for_first_pending_step(): void
    {
        $step = $this->batch->steps()->where('step_number', 1)->first();

        $this->assertTrue($step->canStart());
    }

    public function test_can_start_returns_false_when_already_in_progress(): void
    {
        $step = $this->batch->steps()->where('step_number', 1)->first();
        $step->update(['status' => BatchStep::STATUS_IN_PROGRESS]);

        $this->assertFalse($step->fresh()->canStart());
    }

    public function test_can_start_returns_false_when_already_done(): void
    {
        $step = $this->batch->steps()->where('step_number', 1)->first();
        $step->update(['status' => BatchStep::STATUS_DONE]);

        $this->assertFalse($step->fresh()->canStart());
    }

    public function test_can_start_returns_false_for_second_step_when_first_pending(): void
    {
        $secondStep = $this->batch->steps()->where('step_number', 2)->first();

        $this->assertFalse($secondStep->canStart());
    }

    public function test_can_start_returns_true_for_second_step_when_first_done(): void
    {
        $this->batch->steps()->where('step_number', 1)->update([
            'status'       => BatchStep::STATUS_DONE,
            'started_at'   => now()->subMinutes(30),
            'completed_at' => now(),
        ]);

        $secondStep = $this->batch->steps()->where('step_number', 2)->first();

        $this->assertTrue($secondStep->canStart());
    }

    public function test_can_start_returns_true_for_second_step_when_first_skipped(): void
    {
        $this->batch->steps()->where('step_number', 1)->update([
            'status' => BatchStep::STATUS_SKIPPED,
        ]);

        $secondStep = $this->batch->steps()->where('step_number', 2)->first();

        $this->assertTrue($secondStep->canStart());
    }

    public function test_can_start_returns_false_when_work_order_blocked(): void
    {
        $blockingType = IssueType::factory()->create(['code' => 'BLK', 'is_blocking' => true]);
        \App\Models\Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $blockingType->id,
            'status'        => \App\Models\Issue::STATUS_OPEN,
        ]);
        $this->workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);

        $step = $this->batch->steps()->where('step_number', 1)->first();

        $this->assertFalse($step->canStart());
    }

    // ── canComplete() ────────────────────────────────────────────────────────

    public function test_can_complete_returns_true_when_in_progress(): void
    {
        $step = $this->batch->steps()->where('step_number', 1)->first();
        $step->update(['status' => BatchStep::STATUS_IN_PROGRESS]);

        $this->assertTrue($step->fresh()->canComplete());
    }

    public function test_can_complete_returns_false_when_pending(): void
    {
        $step = $this->batch->steps()->where('step_number', 1)->first();

        $this->assertFalse($step->canComplete());
    }

    public function test_can_complete_returns_false_when_done(): void
    {
        $step = $this->batch->steps()->where('step_number', 1)->first();
        $step->update(['status' => BatchStep::STATUS_DONE]);

        $this->assertFalse($step->fresh()->canComplete());
    }

    // ── Casts ────────────────────────────────────────────────────────────────

    public function test_step_number_is_cast_to_integer(): void
    {
        $step = $this->batch->steps()->first();

        $this->assertIsInt($step->step_number);
    }

    public function test_started_at_is_cast_to_datetime(): void
    {
        $step = $this->batch->steps()->first();
        $step->update(['started_at' => now()]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $step->fresh()->started_at);
    }

    public function test_duration_minutes_is_cast_to_integer(): void
    {
        $step = $this->batch->steps()->first();
        $step->update(['duration_minutes' => 15]);

        $this->assertIsInt($step->fresh()->duration_minutes);
    }

    // ── Scope ────────────────────────────────────────────────────────────────

    public function test_scope_status_filters_correctly(): void
    {
        $steps = $this->batch->steps()->orderBy('step_number')->get();

        // Put step 1 in progress
        $steps->first()->update(['status' => BatchStep::STATUS_IN_PROGRESS]);

        $inProgress = $this->batch->steps()->status(BatchStep::STATUS_IN_PROGRESS)->get();

        $this->assertCount(1, $inProgress);
        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $inProgress->first()->status);
    }
}
