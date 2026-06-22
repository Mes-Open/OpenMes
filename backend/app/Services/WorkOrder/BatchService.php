<?php

namespace App\Services\WorkOrder;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\QualityControlTask;
use App\Models\User;
use App\Services\Material\MaterialAllocationService;
use App\Services\Quality\QualityTriggerService;
use Illuminate\Support\Facades\DB;

class BatchService
{
    public function __construct(
        protected WorkOrderService $workOrderService,
        protected MaterialAllocationService $allocationService,
        protected QualityTriggerService $qualityTriggerService,
    ) {}

    /**
     * Start a batch step.
     *
     * @param  array<int, array<int, array{material_lot_id: int|string, picked_qty: int|float|string}>>  $picksByMaterial
     *                                                                                                                     Operator-chosen lot picks keyed by material id (WO-time "suggest +
     *                                                                                                                     override"). Empty → automatic FEFO/FIFO/LIFO picking as before.
     *
     * @throws \Exception
     */
    public function startStep(BatchStep $step, User $user, array $picksByMaterial = []): BatchStep
    {
        return DB::transaction(function () use ($step, $user, $picksByMaterial) {
            // Enforce workstation routing (if enabled)
            $this->guardWorkstationRouting($step, $user);

            // Hard gate: an outstanding blocking quality control must be done
            // before more work happens on this batch (#105).
            if (QualityControlTask::hasOpenBlockingForBatch($step->batch_id)) {
                throw new \Exception(__('A required quality control is outstanding for this batch and must be completed first.'));
            }

            // Validate step can be started
            if (! $step->canStart()) {
                $this->throwValidationError($step);
            }

            $batch = $step->batch;
            $wasPending = $batch->status === Batch::STATUS_PENDING;

            // Start the step
            $step->update([
                'status' => BatchStep::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'started_by_id' => $user->id,
            ]);

            // Update batch status
            $this->updateBatchStatus($batch);

            // Allocate materials when batch first transitions to IN_PROGRESS
            // (covers BOM rows with consumed_at='start' or unspecified). Attribute
            // these allocations to this (first) step for genealogy.
            if ($wasPending && $batch->fresh()->status === Batch::STATUS_IN_PROGRESS) {
                $this->allocationService->allocateForBatch($batch, $user, $picksByMaterial, attributeStepId: $step->id);
            }

            // Always check for BOM rows targeted at *this* step (consumed_at='during').
            $this->allocationService->allocateForStep($step, $user, $picksByMaterial);

            // Update work order status
            $this->workOrderService->updateWorkOrderStatus($batch->workOrder);

            // Quality-control triggers: batch just entered production (#105).
            if ($wasPending && $batch->fresh()->status === Batch::STATUS_IN_PROGRESS) {
                $this->qualityTriggerService->fireInProduction($batch->fresh());
            }

            return $step->fresh();
        });
    }

    /**
     * Complete a batch step.
     *
     * @throws \Exception
     */
    public function completeStep(BatchStep $step, User $user, array $data = []): BatchStep
    {
        return DB::transaction(function () use ($step, $user, $data) {
            // Enforce workstation routing (if enabled)
            $this->guardWorkstationRouting($step, $user);

            // Validate step can be completed
            if (! $step->canComplete()) {
                throw new \Exception('Step cannot be completed. Current status: '.$step->status);
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

            // The next step (prerequisites now met) becomes READY.
            $batch->promoteReadySteps();

            // If batch is complete, update produced quantity and consume materials
            if ($batch->status === Batch::STATUS_DONE) {
                // End-of-batch BOM rows (consumed_at='end') get allocated now,
                // immediately before everything is marked consumed. Attribute to
                // the completing step so the genealogy bridge has a step to record.
                $this->allocationService->allocateForBatchEnd($batch, $user, attributeStepId: $step->id);
                $this->completeBatch($batch, $data['produced_qty'] ?? $batch->target_qty);
                $this->allocationService->consumeForBatch($batch);

                // Quality-control triggers: every-N-units checks (#105).
                $this->qualityTriggerService->fireForUnits($batch->fresh());
            }

            // Update work order status
            $this->workOrderService->updateWorkOrderStatus($batch->workOrder);

            return $step->fresh();
        });
    }

    /**
     * Skip an optional step (or a variant-group member). Records who/when and an
     * optional reason. Sequential enforcement already treats SKIPPED like DONE,
     * so the next step unblocks.
     *
     * @throws \Exception
     */
    public function skipStep(BatchStep $step, User $user, ?string $reason = null): BatchStep
    {
        return DB::transaction(function () use ($step, $user, $reason) {
            $this->guardWorkstationRouting($step, $user);

            if (! $step->canSkip()) {
                throw new \Exception('This step is required and cannot be skipped.');
            }

            $step->update([
                'status' => BatchStep::STATUS_SKIPPED,
                'skip_reason' => $reason,
                'completed_at' => now(),
                'completed_by_id' => $user->id,
            ]);

            $this->updateBatchStatus($step->batch);
            // Skipping a step unblocks the next one (SKIPPED counts like DONE).
            $step->batch->promoteReadySteps();
            $this->workOrderService->updateWorkOrderStatus($step->batch->workOrder);

            return $step->fresh();
        });
    }

    /**
     * Choose a variant within a group: activate this step and skip its siblings.
     * Lets the operator override the template's default variant.
     *
     * @throws \Exception
     */
    public function chooseVariant(BatchStep $step, User $user): BatchStep
    {
        return DB::transaction(function () use ($step, $user) {
            if ($step->variant_group === null) {
                throw new \Exception('This step is not part of a variant group.');
            }

            if ($step->status === BatchStep::STATUS_DONE) {
                throw new \Exception('This variant is already completed.');
            }

            // Activate the chosen variant, skip every sibling not already done.
            $step->update(['status' => BatchStep::STATUS_PENDING, 'skip_reason' => null]);

            $step->variantSiblings()
                ->where('status', '!=', BatchStep::STATUS_DONE)
                ->update([
                    'status' => BatchStep::STATUS_SKIPPED,
                    'completed_at' => now(),
                    'completed_by_id' => $user->id,
                ]);

            $this->updateBatchStatus($step->batch);
            // Promote the chosen variant to READY if it's next in line.
            $step->batch->promoteReadySteps();

            return $step->fresh();
        });
    }

    /**
     * Report a problem on a step (creates an issue).
     *
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
     * Enforce workstation routing: when enabled, a workstation-bound operator
     * may only start/complete steps assigned to their own workstation.
     *
     * Bypassed for Admins/Supervisors and for line-level operators (users with
     * no workstation assigned). Steps without an assigned workstation are open
     * to anyone. This is the single server-side chokepoint covering both the
     * Livewire UI and the REST API, since both route through BatchService.
     *
     * @throws \Exception
     */
    protected function guardWorkstationRouting(BatchStep $step, User $user): void
    {
        $enabled = json_decode(
            DB::table('system_settings')->where('key', 'workstation_routing_enabled')->value('value') ?? 'false',
            true
        ) ?? false;

        if (! $enabled || ! $step->workstation_id) {
            return;
        }

        // Admins and Supervisors can operate any workstation.
        if ($user->hasRole('Admin') || $user->hasRole('Supervisor')) {
            return;
        }

        // Line-level operators (no workstation assigned) are not restricted.
        if (! $user->workstation_id) {
            return;
        }

        if ((int) $step->workstation_id !== (int) $user->workstation_id) {
            $stationName = $step->workstation?->name ?? __('another workstation');
            throw new \Exception(
                __('This step is assigned to :station and will appear in that workstation\'s queue.', ['station' => $stationName])
            );
        }
    }

    /**
     * Throw appropriate validation error based on step state.
     *
     * @throws \Exception
     */
    protected function throwValidationError(BatchStep $step): void
    {
        if (! in_array($step->status, [BatchStep::STATUS_PENDING, BatchStep::STATUS_READY], true)) {
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

            if (! $previousStep || ! in_array($previousStep->status, [BatchStep::STATUS_DONE, BatchStep::STATUS_SKIPPED])) {
                $prevNum = $step->step_number - 1;
                throw new \Exception('must be completed before');
            }
        }

        throw new \Exception('Step cannot be started');
    }
}
