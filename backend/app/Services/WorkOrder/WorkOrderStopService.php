<?php

namespace App\Services\WorkOrder;

use App\Enums\ChangeRequestStatus;
use App\Enums\WorkOrderStopType;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\ProductionDowntime;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use App\Models\WorkOrderStop;
use Illuminate\Support\Facades\DB;

/**
 * Structured production stops and controlled resume (#182).
 *
 * The plain `IN_PROGRESS → PAUSED` transition records that production stopped but
 * not why, in what state, or under which configuration — which is what made it
 * impossible to answer "what was built before the change" months later. A stop
 * records all three, as a photograph: quantities, batches and the snapshot version
 * are copied onto the stop and never read back into execution.
 *
 * The legacy pause/resume path still works: an order paused without a stop record
 * resumes here without one too, so nothing that exists today starts demanding a
 * change request.
 */
class WorkOrderStopService
{
    public function __construct(
        protected WorkOrderSnapshotService $snapshots,
    ) {}

    /**
     * Stop production on a running order.
     *
     * `requires_change` is what separates the two worlds: it puts the order on
     * CHANGE_HOLD instead of PAUSED, and resume() will then refuse to restart
     * production until an approved change request has actually been applied.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \DomainException When the order is not running, or is already stopped.
     */
    public function stop(WorkOrder $workOrder, array $data, User $user): WorkOrderStop
    {
        return DB::transaction(function () use ($workOrder, $data, $user) {
            WorkOrder::whereKey($workOrder->getKey())->lockForUpdate()->first();
            $workOrder->refresh();

            if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
                throw new \DomainException('Only IN_PROGRESS work orders can be stopped.');
            }

            // One open stop at a time, or "when did production restart" stops having
            // a single answer and downtime totals double-count.
            if ($workOrder->openStop() !== null) {
                throw new \DomainException('This work order already has an open production stop.');
            }

            $type = $data['type'] instanceof WorkOrderStopType
                ? $data['type']
                : WorkOrderStopType::from($data['type']);

            $requiresChange = (bool) ($data['requires_change'] ?? false);

            // Baseline the configuration before photographing its version, so an
            // order released before change control still reports a real version.
            $this->snapshots->ensureBaseline($workOrder, $user);
            $workOrder->refresh();

            $stop = WorkOrderStop::create([
                'work_order_id' => $workOrder->id,
                'batch_id' => $data['batch_id'] ?? null,
                'type' => $type,
                'reason' => $data['reason'],
                'requires_change' => $requiresChange,
                'produced_qty_at_stop' => (float) $workOrder->produced_qty,
                'snapshot_version_at_stop' => $workOrder->snapshot_version,
                'context' => $this->captureContext($workOrder),
                'issue_id' => $data['issue_id'] ?? null,
                'production_downtime_id' => $this->openDowntime($workOrder, $data, $user)?->id,
                'stopped_by_id' => $user->id,
                'stopped_at' => now(),
                'tenant_id' => $workOrder->tenant_id,
            ]);

            $workOrder->update([
                'status' => $requiresChange
                    ? WorkOrder::STATUS_CHANGE_HOLD
                    : WorkOrder::STATUS_PAUSED,
            ]);

            return $stop->fresh();
        });
    }

    /**
     * Resume production.
     *
     * Returns the stop that was closed, or null when the order was paused the legacy
     * way (no stop record) — the caller still gets an IN_PROGRESS order either way.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \DomainException When the order is not stopped, or a required change has not been applied.
     */
    public function resume(WorkOrder $workOrder, array $data, User $user): ?WorkOrderStop
    {
        return DB::transaction(function () use ($workOrder, $data, $user) {
            WorkOrder::whereKey($workOrder->getKey())->lockForUpdate()->first();
            $workOrder->refresh();

            if (! in_array($workOrder->status, WorkOrder::HELD_STATUSES, true)) {
                throw new \DomainException('Only PAUSED, BLOCKED or CHANGE_HOLD work orders can be resumed.');
            }

            // BLOCKED belongs to the issue workflow, not to this one: it is set when a
            // blocking issue is reported and cleared when that issue is resolved.
            // Resuming past it here would put the order back into production with the
            // blocking issue still open, and nothing would re-block it.
            if ($workOrder->status === WorkOrder::STATUS_BLOCKED && $workOrder->isBlocked()) {
                throw new \DomainException(
                    'This work order is blocked by an open blocking issue. Resolve the issue to unblock it.'
                );
            }

            $stop = $workOrder->openStop();
            $changeRequest = $this->resolveChangeRequest($workOrder, $data, $stop);

            // The gate the whole feature exists for: an order held for a configuration
            // change may not restart on the old configuration just because somebody
            // pressed resume.
            if ($this->needsAppliedChange($workOrder, $stop) && $changeRequest === null) {
                throw new \DomainException(
                    'This work order is on change hold: an approved change request must be applied before resuming.'
                );
            }

            if ($stop !== null) {
                $resumedAt = now();

                $stop->update([
                    'resumed_by_id' => $user->id,
                    'resumed_at' => $resumedAt,
                    'resume_notes' => $data['notes'] ?? null,
                    'duration_minutes' => (int) abs($stop->stopped_at->diffInMinutes($resumedAt)),
                    'applied_change_request_id' => $changeRequest?->id,
                    'resulting_status' => WorkOrder::STATUS_IN_PROGRESS,
                ]);

                // A linked downtime measures how long the resource was idle, which is
                // the same interval — close it with the stop rather than leaving it
                // running and inflating OEE loss.
                $stop->downtime?->stop();
            }

            $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

            return $stop?->fresh();
        });
    }

    /**
     * Whether resuming requires an applied change request: the order is on change
     * hold, or the open stop was raised as one needing a configuration change.
     */
    protected function needsAppliedChange(WorkOrder $workOrder, ?WorkOrderStop $stop): bool
    {
        return $workOrder->isOnChangeHold() || (bool) $stop?->requires_change;
    }

    /**
     * Resolve the change request quoted on the resume payload, insisting it is one
     * of this order's, that it has actually been applied — an approved-but-not-applied
     * request means the shop floor is still holding the old configuration — and that
     * it was applied for THIS stop.
     *
     * That last check is what stops a stale request from unlocking a later hold: an
     * order changed and resumed last month must not be able to quote that same change
     * to walk out of today's change hold without anything having been changed.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \DomainException
     */
    protected function resolveChangeRequest(WorkOrder $workOrder, array $data, ?WorkOrderStop $stop): ?WorkOrderChangeRequest
    {
        $id = $data['change_request_id'] ?? null;

        if ($id === null) {
            // No id given: find the applied change that resolves this stop ourselves,
            // so a plain "resume" from a list screen works the same as the one from
            // the review page, which does carry the id. The guard is unaffected — if
            // no change has been applied since the stop, this is still null and the
            // caller is still refused.
            return $this->appliedChangeResolving($workOrder, $stop);
        }

        $changeRequest = WorkOrderChangeRequest::where('work_order_id', $workOrder->id)
            ->whereKey($id)
            ->first();

        if ($changeRequest === null) {
            throw new \DomainException('The referenced change request does not belong to this work order.');
        }

        if ($changeRequest->status !== ChangeRequestStatus::Applied) {
            throw new \DomainException('The referenced change request has not been applied yet.');
        }

        if ($stop !== null && $changeRequest->applied_at?->lt($stop->stopped_at)) {
            throw new \DomainException(
                'The referenced change request was applied before this stop and does not resolve it.'
            );
        }

        return $changeRequest;
    }

    /**
     * The applied change request that unblocks this stop: the most recent one applied
     * at or after the stop began. Null when nothing has been applied since, which is
     * exactly the case the change-hold guard must refuse.
     */
    protected function appliedChangeResolving(WorkOrder $workOrder, ?WorkOrderStop $stop): ?WorkOrderChangeRequest
    {
        return WorkOrderChangeRequest::where('work_order_id', $workOrder->id)
            ->where('status', ChangeRequestStatus::Applied->value)
            ->when($stop !== null, fn ($q) => $q->where('applied_at', '>=', $stop->stopped_at))
            ->orderByDesc('applied_at')
            ->first();
    }

    /**
     * Open a downtime record alongside the stop when the caller supplied a reason.
     *
     * A stop and a downtime answer different questions (why production stopped versus
     * how long a resource was idle), so they are linked rather than merged — and a
     * downtime is only created when somebody actually classified it, never invented
     * here to make a report look complete.
     *
     * @param  array<string, mixed>  $data
     */
    protected function openDowntime(WorkOrder $workOrder, array $data, User $user): ?ProductionDowntime
    {
        $reasonId = $data['downtime_reason_id'] ?? null;

        if ($reasonId === null) {
            return null;
        }

        // A downtime is booked against a line, and line_id is nullable on a work
        // order. Refuse rather than saving the stop and silently dropping the
        // downtime the caller asked for — the gap would only surface much later,
        // in downtime reporting.
        if ($workOrder->line_id === null) {
            throw new \DomainException(
                'This work order has no production line, so a downtime record cannot be opened for it.'
            );
        }

        return ProductionDowntime::create([
            'line_id' => $workOrder->line_id,
            'downtime_reason_id' => $reasonId,
            'started_at' => now(),
            'notes' => $data['reason'] ?? null,
            'reported_by' => $user->id,
            'tenant_id' => $workOrder->tenant_id,
        ]);
    }

    /**
     * Photograph the execution state at the moment of the stop: what was produced,
     * which batches and steps were where, and what material had been committed.
     *
     * Reported only. Nothing in this array is ever read back into execution, which is
     * what guarantees a stop cannot rewrite what the shop floor already did.
     *
     * @return array<string, mixed>
     */
    protected function captureContext(WorkOrder $workOrder): array
    {
        $batches = $workOrder->batches()->get(['id', 'batch_number', 'status', 'target_qty', 'produced_qty', 'snapshot_version']);

        $activeSteps = BatchStep::whereIn('batch_id', $batches->pluck('id'))
            ->where('status', BatchStep::STATUS_IN_PROGRESS)
            ->get(['id', 'batch_id', 'step_number', 'name', 'status']);

        $allocations = $workOrder->materialAllocations()
            ->selectRaw('material_id, SUM(allocated_qty) as allocated_qty, SUM(consumed_qty) as consumed_qty')
            ->groupBy('material_id')
            ->get();

        return [
            'produced_qty' => (float) $workOrder->produced_qty,
            'planned_qty' => (float) $workOrder->planned_qty,
            'remaining_qty' => max(0.0, (float) $workOrder->planned_qty - (float) $workOrder->produced_qty),
            'product_revision_id' => $workOrder->product_revision_id,
            'batches' => $batches->map(fn (Batch $b) => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'status' => $b->status,
                'target_qty' => (float) $b->target_qty,
                'produced_qty' => (float) $b->produced_qty,
                'snapshot_version' => $b->snapshot_version,
            ])->values()->all(),
            'active_steps' => $activeSteps->map(fn (BatchStep $s) => [
                'id' => $s->id,
                'batch_id' => $s->batch_id,
                'step_number' => $s->step_number,
                'name' => $s->name,
            ])->values()->all(),
            'materials' => $allocations->map(fn ($row) => [
                'material_id' => (int) $row->material_id,
                'allocated_qty' => (float) $row->allocated_qty,
                'consumed_qty' => (float) $row->consumed_qty,
            ])->values()->all(),
        ];
    }
}
