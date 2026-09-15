<?php

namespace App\Services\WorkOrder;

use App\Models\BatchStep;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/** Applies explicit good-output deltas; raw machine readings belong to MachineCounterService. */
class MachineProductionService
{
    /** Direct output increments are reserved for whole-batch orders. Raw readings use MachineCounterService. */
    public function recordGoodCount(WorkOrder $workOrder, float $delta, ?BatchStep $step = null): bool
    {
        if (! is_finite($delta) || $delta <= 0) {
            return false;
        }

        return DB::transaction(function () use ($workOrder, $delta, $step) {
            $current = WorkOrder::whereKey($workOrder->id)->lockForUpdate()->firstOrFail();
            if (! $current->isMachineCounted() || in_array($current->status, WorkOrder::TERMINAL_STATUSES, true)) {
                return false;
            }
            if ($current->usesStepLedger()) {
                return $step && $step->batch?->work_order_id === $current->id
                    && app(BatchService::class)->recordMachinePass($step, $delta) > 0;
            }
            $this->setProducedQty($current, (float) $current->produced_qty + $delta);

            return true;
        });
    }

    /** A source-free absolute value cannot safely be converted to production. */
    public function recordAbsoluteCount(WorkOrder $workOrder, float $value): bool
    {
        return false;
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
