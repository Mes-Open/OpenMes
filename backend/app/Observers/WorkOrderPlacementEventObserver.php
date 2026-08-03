<?php

namespace App\Observers;

use App\Events\Schedule\WorkOrderScheduled;
use App\Models\WorkOrderPlacement;
use Illuminate\Support\Facades\Log;

/**
 * An order's extra segments are where it runs beyond its primary line, so adding,
 * moving or dropping one is a schedule change just like moving the order itself.
 *
 * Those live in their own table, so a segment-only edit changes no column on
 * `work_orders` and WorkOrderEventObserver (which watches
 * WorkOrder::PLACEMENT_FIELDS) never sees it. Watching the segment model covers
 * the gap for every path — the planner's multi-line drag, the mobile API, and an
 * undo that only restores segments.
 */
class WorkOrderPlacementEventObserver
{
    public function created(WorkOrderPlacement $placement): void
    {
        $this->fire($placement);
    }

    public function updated(WorkOrderPlacement $placement): void
    {
        $this->fire($placement);
    }

    public function deleted(WorkOrderPlacement $placement): void
    {
        $this->fire($placement);
    }

    /**
     * Dispatch for the owning order, carrying the segment's own changes so a
     * listener can tell what moved. Guarded like the other module hooks: a
     * throwing listener must never break the planner write that triggered it.
     */
    private function fire(WorkOrderPlacement $placement): void
    {
        try {
            $workOrder = $placement->workOrder;
            if ($workOrder === null) {
                return; // parent already gone (cascade delete) — nothing to report
            }

            WorkOrderScheduled::dispatch($workOrder, $placement->getChanges());
        } catch (\Throwable $e) {
            Log::warning('WorkOrder placement hook failed', ['error' => $e->getMessage()]);
        }
    }
}
