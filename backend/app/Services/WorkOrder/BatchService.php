<?php

namespace App\Services\WorkOrder;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\QualityControlTask;
use App\Models\ScrapEntry;
use App\Models\Shift;
use App\Models\User;
use App\Models\Workstation;
use App\Services\Material\MaterialAllocationService;
use App\Services\Quality\QualityTriggerService;
use App\Support\ProductionFlow;
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
            $step = $this->lockProductionStep($step);
            if (ProductionFlow::isTransfer() && ($blocker = $step->productionBlocker())) {
                throw new \DomainException($blocker);
            }

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
            $step = $this->lockProductionStep($step);
            if (ProductionFlow::isTransfer() && ($blocker = $step->productionBlocker())) {
                throw new \DomainException($blocker);
            }

            // Enforce workstation routing (if enabled)
            $this->guardWorkstationRouting($step, $user);

            // Validate step can be completed
            if (! $step->canComplete()) {
                throw new \Exception('Step cannot be completed. Current status: '.$step->status);
            }

            // Transfer flow: nothing may still be on its way here, and nothing
            // may be left waiting, before the step is closed.
            if ($blocker = $step->completionBlocker()) {
                throw new \DomainException($blocker);
            }

            // Document control: a mandatory, validatable document attached to this
            // step must be validated before the step can be completed.
            $pendingDocs = $step->blockingDocuments()->pluck('name');
            if ($pendingDocs->isNotEmpty()) {
                throw new \Exception(__(
                    'This step is blocked: the mandatory document(s) ":docs" must be validated before it can be completed.',
                    ['docs' => $pendingDocs->implode(', ')],
                ));
            }

            // Work-instruction control: required checklist items on this step must
            // be ticked off before it can be completed.
            $pendingChecklist = $step->pendingRequiredChecklistLabels();
            if ($pendingChecklist->isNotEmpty()) {
                throw new \Exception(__(
                    'This step is blocked: the required checklist item(s) ":items" must be completed before it can be completed.',
                    ['items' => $pendingChecklist->implode(', ')],
                ));
            }

            // Output control: required typed outputs on this step must be recorded
            // by the operator before it can be completed.
            $pendingOutputs = $step->pendingRequiredOutputs();
            if ($pendingOutputs->isNotEmpty()) {
                throw new \Exception(__(
                    'This step is blocked: the required output(s) ":items" must be recorded before it can be completed.',
                    ['items' => $pendingOutputs->implode(', ')],
                ));
            }

            // Read-confirmation control: a step flagged as carrying critical
            // instructions must be acknowledged (read-confirmed) by the operator
            // before it can be completed.
            if ($step->needsReadConfirmation()) {
                throw new \Exception(__(
                    'This step is blocked: you must confirm you have read the critical instructions before it can be completed.'
                ));
            }

            // Recorded time (ISA-95 L3 system value) — the wall-clock diff, kept for
            // audit. Retained regardless of any operator-confirmed actuals below.
            $durationMinutes = null;
            if ($step->started_at) {
                $durationMinutes = (int) abs(now()->diffInMinutes($step->started_at));
            }

            // Operator-confirmed actual times (ISA-95 L3), stored separately from the
            // recorded value and authoritative for performance reporting (#52). The
            // optional setup/run split must fit within the confirmed elapsed total.
            $actualElapsed = $data['actual_elapsed_minutes'] ?? null;
            $actualSetup = $data['actual_setup_minutes'] ?? null;
            $actualRun = $data['actual_run_minutes'] ?? null;
            // A setup/run split is only meaningful against a total: reject a split
            // supplied without an elapsed value so reporting can always verify it.
            if ($actualElapsed === null && ($actualSetup !== null || $actualRun !== null)) {
                throw new \Exception(__('Actual elapsed time is required when setup or run time is provided.'));
            }
            if ($actualElapsed !== null && ((int) $actualSetup + (int) $actualRun) > (int) $actualElapsed) {
                throw new \Exception(__('Actual setup + run time cannot exceed the actual elapsed time.'));
            }

            // Whole-batch flow: finishing a step passes along whatever was not
            // scrapped, so the ledger stays consistent without any quantity
            // logging. (Transfer flow arrives here with nothing left waiting.)
            $remainder = $step->availableQty();

            // Complete the step
            $step->update([
                'status' => BatchStep::STATUS_DONE,
                'passed_qty' => (float) $step->passed_qty + $remainder,
                'completed_at' => now(),
                'completed_by_id' => $user->id,
                'duration_minutes' => $durationMinutes,
                'actual_elapsed_minutes' => $actualElapsed,
                'actual_setup_minutes' => $actualSetup,
                'actual_run_minutes' => $actualRun,
            ]);

            // Update batch status
            $batch = $step->batch;
            $this->updateBatchStatus($batch);

            // The next step (prerequisites now met) becomes READY.
            $batch->promoteReadySteps();

            $this->finishBatchIfComplete($batch, $step, $user, $data);

            // Update work order status
            $this->workOrderService->updateWorkOrderStatus($batch->workOrder);

            return $step->fresh();
        });
    }

    /** Aggregate step totals cannot safely apply an entry-level correction window. */
    public function manualCorrectionsEnabled(): bool
    {
        return json_decode(DB::table('system_settings')->where('key', 'production_qty_edit_policy')->value('value') ?? '"none"', true) === 'full';
    }

    /** Correct a running manual step without erasing the audit trail or consumed output. */
    public function correctGoodQuantity(BatchStep $step, User $user, float $good, float $expected, string $reason): BatchStep
    {
        return DB::transaction(function () use ($step, $user, $good, $expected, $reason) {
            if (! ProductionFlow::isTransfer()) {
                throw new \DomainException(__('Step corrections require transfer flow.'));
            }
            if (! $this->manualCorrectionsEnabled()) {
                throw new \DomainException(__('Step total corrections require the Full edit policy.'));
            }
            $step = $this->lockProductionStep($step);
            $this->loadLedgerContext($step);
            $this->guardWorkstationRouting($step, $user);
            if ((int) $step->started_by_id !== (int) $user->id && ! $user->hasAnyRole(['Admin', 'Supervisor'])) {
                throw new \DomainException(__('Only the operator who started this step or a supervisor can correct its total.'));
            }
            $batch = $step->batch;
            $order = $batch->workOrder;
            if ($order->counting_source !== 'operator' || $step->status !== BatchStep::STATUS_IN_PROGRESS) {
                throw new \DomainException(__('Only running, manually counted steps can be corrected.'));
            }
            if ($blocker = $step->productionBlocker()) {
                throw new \DomainException($blocker);
            }
            if (! is_finite($good) || ! is_finite($expected) || $good < 0 || $good > 99999999 || abs($good - round($good, 2)) > 0.000001 || trim($reason) === '') {
                throw new \DomainException(__('Enter a valid quantity and correction reason.'));
            }
            if (abs((float) $step->passed_qty - $expected) > 0.001) {
                throw new \DomainException(__('The quantity changed. Refresh and review it before correcting.'));
            }
            if ($good + (float) $step->scrap_qty > $step->incomingQty() + 0.001) {
                throw new \DomainException(__('The correction exceeds the incoming quantity.'));
            }
            $downstream = $batch->steps->filter(fn ($s) => $s->step_number > $step->step_number && $s->status !== BatchStep::STATUS_SKIPPED)->sortBy('step_number');
            $next = $downstream->first();
            if ($downstream->contains('status', BatchStep::STATUS_DONE)
                || ($next && $good + 0.001 < (float) $next->passed_qty + (float) $next->scrap_qty)) {
                throw new \DomainException(__('Correct downstream quantities first; these pieces have already been processed.'));
            }
            $before = (float) $step->passed_qty;
            if (abs($before - $good) < 0.001) {
                return $step;
            }
            $step->update(['passed_qty' => $good]);
            \App\Models\AuditLog::create([
                'user_id' => $user->id, 'entity_type' => BatchStep::class, 'entity_id' => $step->id,
                'action' => 'quantity_corrected', 'before_state' => ['passed_qty' => $before],
                'after_state' => ['passed_qty' => $good, 'reason' => trim($reason)],
            ]);
            $batch->promoteReadySteps();
            $this->rollUpProducedQty($batch);
            $this->workOrderService->updateWorkOrderStatus($order->fresh());

            return $step->fresh();
        });
    }

    /**
     * Log pieces leaving a step: `$good` passed to the next station, `$scrap`
     * lost here (recorded as a scrap entry without a reason — the reason can be
     * added later from the scrap report). In transfer flow this is what opens
     * the next step and drives the batch / work-order produced quantity live.
     *
     * @throws \DomainException when the step is not running, nothing is logged,
     *                          or more is logged than is waiting at the step
     */
    public function recordQuantity(BatchStep $step, User $user, float $good, float $scrap = 0, ?string $notes = null): BatchStep
    {
        return DB::transaction(function () use ($step, $user, $good, $scrap, $notes) {
            // Serialise concurrent logs (two operators, or an operator and a
            // sensor) against the same step so the available count can't go negative.
            $step = $this->lockProductionStep($step);
            $this->loadLedgerContext($step);

            $this->guardWorkstationRouting($step, $user);

            if ($step->status !== BatchStep::STATUS_IN_PROGRESS) {
                throw new \DomainException(__('Start the step before logging quantities.'));
            }

            if ($blocker = $step->productionBlocker()) {
                throw new \DomainException($blocker);
            }

            $good = round(max(0, $good), 2);
            $scrap = round(max(0, $scrap), 2);
            if ($good + $scrap <= 0) {
                throw new \DomainException(__('Log at least one good or scrapped piece.'));
            }

            $available = $step->availableQty();
            if ($good + $scrap > $available + 0.001) {
                throw new \DomainException(__('Only :qty pieces are waiting at this step.', ['qty' => self::formatQty($available)]));
            }

            $step->update([
                'passed_qty' => (float) $step->passed_qty + $good,
                'scrap_qty' => (float) $step->scrap_qty + $scrap,
            ]);

            $batch = $step->batch;
            $workOrder = $batch->workOrder;

            if ($scrap > 0) {
                ScrapEntry::create([
                    'work_order_id' => $workOrder->id,
                    'batch_step_id' => $step->id,
                    'scrap_reason_id' => null,
                    'quantity' => $scrap,
                    'shift_id' => Shift::current($workOrder->line_id)?->id,
                    'notes' => $notes,
                    'reported_by' => $user->id,
                    'reported_at' => now(),
                ]);
            }

            // Pieces are now waiting at the next station: open it (transfer flow).
            $batch->promoteReadySteps();

            if (ProductionFlow::isTransfer()) {
                $this->rollUpProducedQty($batch);
                $this->workOrderService->updateWorkOrderStatus($workOrder->fresh());
            }

            return $step->fresh();
        });
    }

    /**
     * Log pieces leaving a station that owns several consecutive steps: the
     * scrap is lost at `$step`, the good pieces are passed through it and then
     * through every following step bound to the same workstation (starting each
     * one as needed), so the operator confirms the station's work once.
     */
    public function recordQuantityThroughStation(BatchStep $step, User $user, float $good, float $scrap = 0, ?string $notes = null): BatchStep
    {
        return DB::transaction(function () use ($step, $user, $good, $scrap, $notes) {
            $first = $this->recordQuantity($step, $user, $good, $scrap, $notes);

            // Only transfer flow can pass pieces into a step whose predecessor is
            // still running; in whole-batch flow the station's next step opens
            // when this one is finished, so this is an ordinary log.
            if ($good <= 0 || ! $first->workstation_id || ! ProductionFlow::isTransfer()) {
                return $first;
            }

            $following = $first->batch->steps()
                ->where('step_number', '>', $first->step_number)
                ->where('status', '!=', BatchStep::STATUS_SKIPPED)
                ->orderBy('step_number')
                ->get();

            foreach ($following as $next) {
                if ((int) $next->workstation_id !== (int) $first->workstation_id) {
                    break; // the station's run of steps ends here
                }

                if (in_array($next->status, [BatchStep::STATUS_PENDING, BatchStep::STATUS_READY], true)) {
                    $next = $this->startStep($next, $user);
                }

                $this->recordQuantity($next, $user, $good);
            }

            return $first->fresh();
        });
    }

    /**
     * Pieces counted leaving a step by a machine (break-beam pulse, machine
     * good-count). Whole-batch flow keeps the historical bare counter. Transfer
     * flow treats the count like an operator's good log without the operator
     * gates: it is capped at what is actually waiting at the step (so an
     * over-count can't create pieces downstream), it opens the next station, and
     * it rolls the batch / work-order produced quantity up from the ledger.
     *
     * @return float the quantity actually counted (after the cap)
     */
    public function recordMachinePass(BatchStep $step, float $qty): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        if (! ProductionFlow::isTransfer()) {
            $step->increment('passed_qty', $qty);

            return $qty;
        }

        return DB::transaction(function () use ($step, $qty) {
            $step = $this->lockProductionStep($step);
            $this->loadLedgerContext($step);

            if ($step->productionBlocker() || in_array($step->status, [BatchStep::STATUS_DONE, BatchStep::STATUS_SKIPPED], true)) {
                return 0.0;
            }

            $counted = round(min($qty, $step->availableQty()), 2);
            if ($counted <= 0) {
                return 0.0;
            }

            $step->update(['passed_qty' => (float) $step->passed_qty + $counted]);

            $batch = $step->batch->fresh();
            $batch->promoteReadySteps();
            $this->rollUpProducedQty($batch);

            return $counted;
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
            $step = $this->lockProductionStep($step);
            if (ProductionFlow::isTransfer() && ($blocker = $step->productionBlocker())) {
                throw new \DomainException($blocker);
            }

            $this->guardWorkstationRouting($step, $user);

            if (! $step->canSkip()) {
                throw new \Exception('This step is required and cannot be skipped.');
            }

            $this->guardUnprocessedRouting($step);

            $step->update([
                'status' => BatchStep::STATUS_SKIPPED,
                'skip_reason' => $reason,
                'completed_at' => now(),
                'completed_by_id' => $user->id,
            ]);

            $this->updateBatchStatus($step->batch);
            // Skipping a step unblocks the next one (SKIPPED counts like DONE).
            $step->batch->promoteReadySteps();
            if (ProductionFlow::isTransfer()) {
                $this->rollUpProducedQty($step->batch);
                $this->finishBatchIfComplete($step->batch, $step, $user);
            }
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
            $step = $this->lockProductionStep($step);
            if (ProductionFlow::isTransfer() && ($blocker = $step->productionBlocker())) {
                throw new \DomainException($blocker);
            }

            if ($step->variant_group === null) {
                throw new \Exception('This step is not part of a variant group.');
            }

            if ($step->status === BatchStep::STATUS_DONE) {
                throw new \Exception('This variant is already completed.');
            }

            $this->guardUnprocessedRouting($step);
            foreach ($step->variantSiblings()->get() as $sibling) {
                $this->guardUnprocessedRouting($sibling);
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
     * Pool dispatch (#52): a supervisor assigns a specific workstation to a step
     * that carries only an Equipment Class (workstation_type_id). Valid only while
     * the step is still assignable (PENDING/READY) and the chosen workstation is
     * active and of the required type. Once assigned, guardWorkstationRouting
     * enforces that only operators on that workstation may start the step.
     */
    public function assignWorkstation(BatchStep $step, int $workstationId, User $user): BatchStep
    {
        return DB::transaction(function () use ($step, $workstationId, $user) {
            // Lock the row and re-read its status inside the transaction so a step
            // an operator starts concurrently cannot still receive a workstation.
            $locked = BatchStep::whereKey($step->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [BatchStep::STATUS_PENDING, BatchStep::STATUS_READY], true)) {
                throw new \Exception(__('Only a pending step can be assigned a workstation.'));
            }

            $workstation = Workstation::find($workstationId);
            if (! $workstation || ! $workstation->is_active) {
                throw new \Exception(__('The selected workstation is not available.'));
            }

            if ($locked->workstation_type_id && (int) $workstation->workstation_type_id !== (int) $locked->workstation_type_id) {
                throw new \Exception(__('The selected workstation is not of the required type for this step.'));
            }

            $locked->update([
                'workstation_id' => $workstation->id,
                'assigned_by_id' => $user->id,
                'assigned_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    private function finishBatchIfComplete(Batch $batch, BatchStep $step, User $user, array $data = []): void
    {
        // If batch is complete, update produced quantity and consume materials
        if ($batch->status === Batch::STATUS_DONE) {
            // End-of-batch BOM rows (consumed_at='end') get allocated now,
            // immediately before everything is marked consumed. Attribute to
            // the completing step so the genealogy bridge has a step to record.
            $this->allocationService->allocateForBatchEnd($batch, $user, attributeStepId: $step->id);
            // Transfer flow: the batch produced exactly what left its last step.
            $producedQty = ProductionFlow::isTransfer()
                ? (float) ($batch->lastEffectiveStep()?->passed_qty ?? 0)
                : ($data['produced_qty'] ?? $batch->target_qty);
            $this->completeBatch($batch, $producedQty);
            $this->allocationService->consumeForBatch($batch);

            // Quality-control triggers: every-N-units checks (#105).
            $this->qualityTriggerService->fireForUnits($batch->fresh());
        }

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

        if (ProductionFlow::isTransfer()) {
            $this->rollUpProducedQty($batch);

            return;
        }

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
     * Transfer flow: a batch has produced whatever left its last step so far,
     * and the work order the sum over its live batches — updated as pieces
     * flow, not only when a batch closes.
     */
    protected function rollUpProducedQty(Batch $batch): void
    {
        $batch->update([
            'produced_qty' => (float) ($batch->lastEffectiveStep()?->passed_qty ?? 0),
        ]);

        $workOrder = $batch->workOrder;
        $workOrder->update([
            'produced_qty' => $workOrder->batches()
                ->where('status', '!=', Batch::STATUS_CANCELLED)
                ->sum('produced_qty'),
        ]);
    }

    /** Serialize transfer transitions across batches before reading the order rollup. */
    private function lockProductionStep(BatchStep $step): BatchStep
    {
        if (ProductionFlow::isTransfer()) {
            $orderId = Batch::whereKey($step->batch_id)->value('work_order_id');
            \App\Models\WorkOrder::whereKey($orderId)->lockForUpdate()->firstOrFail();
        }

        return BatchStep::whereKey($step->getKey())->lockForUpdate()->firstOrFail();
    }

    private function guardUnprocessedRouting(BatchStep $step): void
    {
        if (ProductionFlow::isTransfer() && $step->batch->steps()
            ->where('step_number', '>=', $step->step_number)
            ->where(fn ($q) => $q->where('passed_qty', '>', 0)->orWhere('scrap_qty', '>', 0))
            ->exists()) {
            throw new \DomainException(__('A step cannot be skipped or changed after quantities have been recorded at it or downstream.'));
        }
    }

    /**
     * Give the step a batch that already holds all of its steps, so the ledger
     * (incoming / available / blockers) is computed from one query instead of
     * re-querying the batch's steps on every call.
     */
    private function loadLedgerContext(BatchStep $step): void
    {
        $step->setRelation('batch', $step->batch()->with('steps')->firstOrFail());
    }

    private static function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
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
                throw new \Exception(__('Complete step :step before starting this step.', ['step' => $prevNum]));
            }
        }

        throw new \Exception('Step cannot be started');
    }
}
