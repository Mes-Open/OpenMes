<?php

namespace App\Services\WorkOrder;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\WorkOrder;
use App\Models\Workstation;

/**
 * The single authoritative path for applying machine-reported production counts
 * to a work order's produced_qty. Both the protocol-agnostic signal pipeline
 * (MachineSignalIngestor) and the legacy MQTT topic-mapping path (ActionExecutor)
 * funnel through here, so the counting_source guard and the auto-start /
 * auto-complete side effects live in exactly one place — no double-count, no
 * divergent status logic.
 */
class MachineProductionService
{
    /**
     * Resolve the work order a machine at this workstation is currently producing:
     * the batch step in progress at the workstation → its batch → work order.
     * Falls back to an active batch assigned to the workstation. Returns null
     * when nothing is running there (the count is still logged upstream).
     */
    public function resolveActiveWorkOrder(Workstation $workstation): ?WorkOrder
    {
        $step = BatchStep::where('workstation_id', $workstation->id)
            ->where('status', BatchStep::STATUS_IN_PROGRESS)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if ($workOrder = $step?->batch?->workOrder) {
            return $workOrder;
        }

        $batch = Batch::forWorkstation($workstation->id)
            ->where('status', Batch::STATUS_IN_PROGRESS)
            ->orderByDesc('id')
            ->first();

        return $batch?->workOrder;
    }

    /**
     * Add a positive good-count delta to produced_qty, honouring counting_source.
     * Returns true when the count was applied, false when it was ignored (order
     * is operator-counted, terminal, or the delta is non-positive).
     */
    public function recordGoodCount(WorkOrder $workOrder, float $delta): bool
    {
        if ($delta <= 0 || ! $workOrder->isMachineCounted()) {
            return false;
        }

        $this->setProducedQty($workOrder, (float) $workOrder->produced_qty + $delta);

        return true;
    }

    /**
     * Set produced_qty to an absolute machine-reported value, honouring
     * counting_source. Used by the legacy MQTT path when a mapping reports the
     * cumulative total rather than a delta.
     */
    public function recordAbsoluteCount(WorkOrder $workOrder, float $value): bool
    {
        if (! $workOrder->isMachineCounted()) {
            return false;
        }

        $this->setProducedQty($workOrder, $value);

        return true;
    }

    /**
     * Persist a new produced_qty and mirror the operator flow's status side
     * effects: auto-start a not-yet-started order, auto-complete once produced
     * reaches planned. Uses update() (not increment()) so model events fire and
     * the Electric shape broadcast happens automatically.
     */
    private function setProducedQty(WorkOrder $workOrder, float $newProduced): void
    {
        if (in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES, true)) {
            return;
        }

        $newProduced = max(0.0, $newProduced);
        $planned = (float) $workOrder->planned_qty;
        $updates = ['produced_qty' => $newProduced];

        if (in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED], true)) {
            $updates['status'] = WorkOrder::STATUS_IN_PROGRESS;
        }

        if ($planned > 0 && $newProduced >= $planned) {
            $updates['status'] = WorkOrder::STATUS_DONE;
            $updates['completed_at'] = now();
        }

        $workOrder->update($updates);
    }
}
