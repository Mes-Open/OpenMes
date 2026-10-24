<?php

namespace App\Observers;

use App\Models\WorkOrder;
use App\Models\WorkOrderOutputEntry;
use Carbon\Carbon;

/**
 * Records every change to a work order's `produced_qty` in the output ledger
 * (`work_order_output_entries`), so output per hour has one consistent source
 * whichever path produced it.
 *
 * Only Eloquent saves fire this. A query-builder `update()` or `increment()`
 * on produced_qty would bypass the ledger — write produced_qty through the
 * model.
 */
class WorkOrderOutputObserver
{
    /**
     * Not `created`: an order imported with output already on it did not
     * produce that output now, and must not show up in this hour's figures.
     */
    public function updated(WorkOrder $workOrder): void
    {
        if (! $workOrder->wasChanged('produced_qty')) {
            return;
        }

        $total = (float) $workOrder->produced_qty;
        $delta = round($total - (float) $workOrder->getOriginal('produced_qty'), 2);

        if ($delta == 0.0) {
            return;
        }

        // No try/catch: the write belongs to the caller's transaction and rolls
        // back with it; a ledger that silently skipped rows would stop adding
        // up to produced_qty.
        WorkOrderOutputEntry::create([
            // From the order, never auth(): counter daemons and queue workers
            // run without a user.
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'line_id' => $workOrder->line_id,
            'delta' => $delta,
            'produced_total' => $total,
            'recorded_at' => Carbon::now(),
        ]);
    }
}
