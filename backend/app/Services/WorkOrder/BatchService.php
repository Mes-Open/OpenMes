<?php

namespace App\Services\WorkOrder;

use App\Models\BatchStep;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BatchService
{
    public function __construct(
        protected WorkOrderService $workOrderService
    ) {}

    /**
     * Start a batch step.
     *
     * @param BatchStep $step
     * @param User $user
     * @return BatchStep
     * @throws \Exception
     */
    public function startStep(BatchStep $step, User $user): BatchStep
    {
        return DB::transaction(function () use ($step, $user) {
            // Validate step can be started
            if (!$step->canStart()) {
                $this->throwValidationError($step);
            }

            // Start the step
            $step->update([
                'status' => BatchStep::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'started_by_id' => $user->id,
            ]);

            // Update batch status
            $this->updateBatchStatus($step->batch);

            // Update work order status
            $this->workOrderService->updateWorkOrderStatus($step->batch->workOrder);

            return $step->fresh();
        });
    }

    /**
     * Complete a batch step.
     *
     * @param BatchStep $step
     * @param User $user
     * @param array $data
     * @return BatchStep
     * @throws \Exception
     */
    public function completeStep(BatchStep $step, User $user, array $data = []): BatchStep
    {
        return DB::transaction(function () use ($step, $user, $data) {
            // Validate step can be completed
            if (!$step->canComplete()) {
                throw new \Exception('Step cannot be completed. Current status: ' . $step->status);
            }

            // Calculate duration
            $durationMinutes = null;
            if ($step->started_at) {
                $durationMinutes = (int) abs(now()->diffInMinutes($step->started_at));
            }

            // Complete the step
            $step->update([
                'status' => BatchStep::STATUS_DONE,
                'completed_at' => now(),
                'completed_by_id' => $user->id,
                'duration_minutes' => $durationMinutes,
            ]);

            // Update batch status
            $batch = $step->batch;
            $this->updateBatchStatus($batch);

            // If batch is complete, update produced quantity
            if ($batch->status === Batch::STATUS_DONE) {
                $this->completeBatch($batch, $data['produced_qty'] ?? $batch->target_qty);
            }

            // Update work order status
            $this->workOrderService->updateWorkOrderStatus($batch->workOrder);

            return $step->fresh();
        });
    }

    /**
     * Report a problem on a step (creates an issue).
     *
     * @param BatchStep $step
     * @param array $issueData
     * @return \App\Models\Issue
     */
    public function reportProblem(BatchStep $step, array $issueData)
    {
        // This will be implemented in Phase 4: Issue/Andon
        // For now, return a placeholder
        throw new \Exception('Issue reporting will be implemented in Phase 4');
    }

    /**
     * Update batch status based on steps.
     *
     * @param Batch $batch
     * @return void
     */
    protected function updateBatchStatus(Batch $batch): void
    {
        // Check if all steps are complete
        if ($batch->allStepsComplete()) {
            $batch->update([
                'status' => Batch::STATUS_DONE,
                'completed_at' => now(),
            ]);
            return;
        }

        // Check if any step is in progress
        $hasInProgressStep = $batch->steps()
            ->where('status', BatchStep::STATUS_IN_PROGRESS)
            ->exists();

        if ($hasInProgressStep && $batch->status !== Batch::STATUS_IN_PROGRESS) {
            $batch->update([
                'status' => Batch::STATUS_IN_PROGRESS,
                'started_at' => $batch->started_at ?? now(),
            ]);
        }
    }

    /**
     * Complete a batch and update produced quantity.
     *
     * @param Batch $batch
     * @param float $producedQty
     * @return void
     */
    protected function completeBatch(Batch $batch, float $producedQty): void
    {
        // Update batch produced qty
        $batch->update([
            'produced_qty' => $producedQty,
        ]);

        // Update work order produced qty
        $workOrder = $batch->workOrder;
        $totalProduced = $workOrder->batches()
            ->where('status', Batch::STATUS_DONE)
            ->sum('produced_qty');

        $workOrder->update([
            'produced_qty' => $totalProduced,
        ]);
    }

    /**
     * Throw appropriate validation error based on step state.
     *
     * @param BatchStep $step
     * @throws \Exception
     */
    protected function throwValidationError(BatchStep $step): void
    {
        if ($step->status !== BatchStep::STATUS_PENDING) {
            throw new \Exception("Step is already {$step->status}");
        }

        $workOrder = $step->batch->workOrder;
        if ($workOrder->isBlocked()) {
            $issues = $workOrder->openBlockingIssues();
            $issueList = $issues->pluck('title')->join(', ');
            throw new \Exception("Work order is blocked by issues: {$issueList}");
        }

        // Check sequential enforcement
        if (config('openmmes.force_sequential_steps', true) && $step->step_number > 1) {
            $previousStep = $step->batch->steps()
                ->where('step_number', $step->step_number - 1)
                ->first();

            if (!$previousStep || !in_array($previousStep->status, [BatchStep::STATUS_DONE, BatchStep::STATUS_SKIPPED])) {
                $prevNum = $step->step_number - 1;
                throw new \Exception("must be completed before");
            }
        }

        throw new \Exception('Step cannot be started');
    }
}
