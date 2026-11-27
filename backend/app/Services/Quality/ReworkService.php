<?php

namespace App\Services\Quality;

use App\Enums\IssueDisposition;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\ReworkOperation;
use App\Models\ScrapEntry;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Production\StepTimeService;
use Illuminate\Support\Facades\DB;

/**
 * Executable rework routing (#400, item 2): pieces of a non-conformance found
 * at a step go back to an earlier operation, are reworked, and return.
 *
 * The rules that keep quantities and material honest:
 *  - Only pieces still waiting at the source step can be sent to rework, and
 *    they are held there (`rework_held_qty`) while the rework is open: they
 *    cannot be passed on or counted as produced, and the step cannot be
 *    completed until the rework ends.
 *  - Completing the rework releases the accepted pieces back to the source
 *    step, where they continue as normal, and books the rest as scrap there.
 *    Cancelling releases every piece unchanged.
 *  - The target operation's own record (quantities, times, material
 *    allocation and consumption) is never touched: no step is restarted and no
 *    material is allocated again. Time spent on the rework is recorded on its
 *    own (StepTimeService, tagged with the rework).
 */
class ReworkService
{
    public function __construct(private StepTimeService $time) {}

    /**
     * @param  array{quantity: float|int|string, reason: string, target_batch_step_id: int, source_batch_step_id?: int|null}  $data
     *
     * @throws \DomainException
     */
    public function create(Issue $issue, array $data, User $by): ReworkOperation
    {
        return DB::transaction(function () use ($issue, $data, $by) {
            $sourceId = $data['source_batch_step_id'] ?? $issue->batch_step_id;
            if (! $sourceId) {
                throw new \DomainException(__('Choose the step where the defect was found.'));
            }

            // Lock order: work order, step, then the issue.
            $source = $this->lockStep((int) $sourceId);
            $issue = Issue::whereKey($issue->getKey())->lockForUpdate()->firstOrFail();

            $disposition = $issue->disposition ?? IssueDisposition::Pending->value;
            if (! in_array($disposition, [IssueDisposition::Pending->value, IssueDisposition::Rework->value], true)) {
                throw new \DomainException(__('This issue already has another disposition. Only a rework disposition can be sent to rework.'));
            }
            $target = BatchStep::findOrFail((int) $data['target_batch_step_id']);
            $batch = $source->batch;
            $workOrder = $batch->workOrder;

            if ($issue->work_order_id && (int) $issue->work_order_id !== (int) $workOrder->id) {
                throw new \DomainException(__('The step does not belong to the work order of this issue.'));
            }
            if ((int) $target->batch_id !== (int) $source->batch_id) {
                throw new \DomainException(__('The rework operation must belong to the same batch.'));
            }
            if ($target->step_number > $source->step_number) {
                throw new \DomainException(__('Rework goes back to the step where the defect was found or an earlier one.'));
            }
            if ($target->status === BatchStep::STATUS_SKIPPED) {
                throw new \DomainException(__('A skipped step cannot be reworked.'));
            }
            if (in_array($batch->status, [Batch::STATUS_DONE, Batch::STATUS_CANCELLED], true)
                || in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES, true)) {
                throw new \DomainException(__('Production is stopped for this work order.'));
            }

            $quantity = round((float) $data['quantity'], 2);
            $waiting = $source->availableQty();
            if ($quantity > $waiting + 0.001) {
                throw new \DomainException(__('Only :qty pieces are waiting at this step.', ['qty' => self::formatQty($waiting)]));
            }

            if ($issue->non_conforming_qty !== null) {
                $already = (float) $issue->reworkOperations()->where('status', '!=', ReworkOperation::STATUS_CANCELLED)->sum('quantity');
                if ($already + $quantity > (float) $issue->non_conforming_qty + 0.001) {
                    throw new \DomainException(__('Rework cannot exceed the non-conforming quantity of the issue (:qty).', [
                        'qty' => self::formatQty((float) $issue->non_conforming_qty),
                    ]));
                }
            }

            if ($disposition !== IssueDisposition::Rework->value) {
                $issue->update([
                    'disposition' => IssueDisposition::Rework->value,
                    'disposition_by_id' => $by->id,
                    'disposition_at' => now(),
                ]);
            }

            $rework = ReworkOperation::create([
                'issue_id' => $issue->id,
                'work_order_id' => $workOrder->id,
                'batch_id' => $batch->id,
                'source_batch_step_id' => $source->id,
                'target_batch_step_id' => $target->id,
                'quantity' => $quantity,
                'reason' => $data['reason'],
                'status' => ReworkOperation::STATUS_OPEN,
                'created_by_id' => $by->id,
            ]);

            $source->update(['rework_held_qty' => round((float) $source->rework_held_qty + $quantity, 2)]);

            return $rework;
        });
    }

    /** @throws \DomainException */
    public function start(ReworkOperation $rework, User $by): ReworkOperation
    {
        return DB::transaction(function () use ($rework, $by) {
            $rework = $this->lock($rework);
            if ($rework->status !== ReworkOperation::STATUS_OPEN) {
                throw new \DomainException(__('Only an open rework can be started.'));
            }
            $this->assertProductionRunning($rework);

            $rework->update([
                'status' => ReworkOperation::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'started_by_id' => $by->id,
            ]);

            return $rework;
        });
    }

    /**
     * End the rework: `accepted` pieces return to the source step, the rest are
     * scrapped there. Accepted + scrapped must equal the rework's quantity.
     *
     * @throws \DomainException
     */
    public function complete(ReworkOperation $rework, float $accepted, float $scrapped, User $by, ?int $scrapReasonId = null, ?string $notes = null): ReworkOperation
    {
        return DB::transaction(function () use ($rework, $accepted, $scrapped, $by, $scrapReasonId, $notes) {
            $rework = $this->lock($rework);
            if ($rework->status !== ReworkOperation::STATUS_IN_PROGRESS) {
                throw new \DomainException(__('Start the rework before completing it.'));
            }
            $this->assertProductionRunning($rework);

            $accepted = round($accepted, 2);
            $scrapped = round($scrapped, 2);
            if (abs($accepted + $scrapped - (float) $rework->quantity) > 0.001) {
                throw new \DomainException(__('Accepted and scrapped pieces must add up to :qty.', ['qty' => self::formatQty((float) $rework->quantity)]));
            }

            $source = $this->lockStep($rework->source_batch_step_id);
            $workOrder = $rework->workOrder;

            $scrapEntry = null;
            if ($scrapped > 0) {
                $scrapEntry = ScrapEntry::create([
                    'work_order_id' => $workOrder->id,
                    'batch_step_id' => $source->id,
                    'scrap_reason_id' => $scrapReasonId,
                    'quantity' => $scrapped,
                    'shift_id' => Shift::current($workOrder->line_id)?->id,
                    'notes' => trim(__('Rework #:id', ['id' => $rework->id]).' '.($notes ?? '')),
                    'reported_by' => $by->id,
                    'reported_at' => now(),
                ]);
            }

            $source->update([
                'rework_held_qty' => max(0, round((float) $source->rework_held_qty - (float) $rework->quantity, 2)),
                'scrap_qty' => round((float) $source->scrap_qty + $scrapped, 2),
            ]);

            $this->time->closeOpenForRework($rework, $by);

            $rework->update([
                'status' => ReworkOperation::STATUS_COMPLETED,
                'accepted_qty' => $accepted,
                'scrapped_qty' => $scrapped,
                'scrap_entry_id' => $scrapEntry?->id,
                'notes' => $notes,
                'completed_at' => now(),
                'completed_by_id' => $by->id,
            ]);

            // Accepted pieces are waiting at the source step again: in transfer
            // flow the next station may open.
            $source->batch->promoteReadySteps();

            return $rework->fresh();
        });
    }

    /** Release every held piece unchanged — the rework is not done. @throws \DomainException */
    public function cancel(ReworkOperation $rework, string $reason, User $by): ReworkOperation
    {
        return DB::transaction(function () use ($rework, $reason, $by) {
            $rework = $this->lock($rework);
            if (! $rework->isActive()) {
                throw new \DomainException(__('This rework is already finished.'));
            }

            $source = $this->lockStep($rework->source_batch_step_id);
            $source->update([
                'rework_held_qty' => max(0, round((float) $source->rework_held_qty - (float) $rework->quantity, 2)),
            ]);

            $this->time->closeOpenForRework($rework, $by);

            $rework->update([
                'status' => ReworkOperation::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_id' => $by->id,
                'cancel_reason' => $reason,
            ]);

            $source->batch->promoteReadySteps();

            return $rework->fresh();
        });
    }

    /**
     * Rework cannot be started or completed once its batch or work order has
     * stopped (cancelling it, which only releases the pieces, stays possible).
     *
     * @throws \DomainException
     */
    private function assertProductionRunning(ReworkOperation $rework): void
    {
        $batch = $rework->batch;
        $workOrder = $rework->workOrder;
        if (in_array($batch?->status, [Batch::STATUS_DONE, Batch::STATUS_CANCELLED], true)
            || in_array($workOrder?->status, WorkOrder::TERMINAL_STATUSES, true)) {
            throw new \DomainException(__('Production is stopped for this work order.'));
        }
    }

    /**
     * The API / page shape of a rework operation.
     *
     * @return array<string, mixed>
     */
    public static function present(ReworkOperation $r): array
    {
        $step = fn (?BatchStep $s) => $s ? ['id' => $s->id, 'step_number' => $s->step_number, 'name' => $s->name] : null;

        return [
            'id' => $r->id,
            'issue_id' => $r->issue_id,
            'work_order_id' => $r->work_order_id,
            'batch_id' => $r->batch_id,
            'source_step' => $step($r->sourceStep),
            'target_step' => $step($r->targetStep),
            'quantity' => (float) $r->quantity,
            'reason' => $r->reason,
            'status' => $r->status,
            'accepted_qty' => $r->accepted_qty !== null ? (float) $r->accepted_qty : null,
            'scrapped_qty' => $r->scrapped_qty !== null ? (float) $r->scrapped_qty : null,
            'scrap_entry_id' => $r->scrap_entry_id,
            'notes' => $r->notes,
            'actual_labor_minutes' => $r->actual_labor_minutes,
            'actual_machine_minutes' => $r->actual_machine_minutes,
            'created_by_id' => $r->created_by_id,
            'started_at' => $r->started_at?->toIso8601String(),
            'started_by_id' => $r->started_by_id,
            'completed_at' => $r->completed_at?->toIso8601String(),
            'completed_by_id' => $r->completed_by_id,
            'cancelled_at' => $r->cancelled_at?->toIso8601String(),
            'cancelled_by_id' => $r->cancelled_by_id,
            'cancel_reason' => $r->cancel_reason,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    private function lock(ReworkOperation $rework): ReworkOperation
    {
        return ReworkOperation::whereKey($rework->getKey())->lockForUpdate()->firstOrFail();
    }

    /** Same lock order as BatchService: the work order first, then the step. */
    private function lockStep(int $stepId): BatchStep
    {
        $orderId = Batch::whereKey(BatchStep::whereKey($stepId)->value('batch_id'))->value('work_order_id');
        if ($orderId) {
            WorkOrder::whereKey($orderId)->lockForUpdate()->first();
        }

        return BatchStep::whereKey($stepId)->lockForUpdate()->firstOrFail();
    }

    private static function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }
}
