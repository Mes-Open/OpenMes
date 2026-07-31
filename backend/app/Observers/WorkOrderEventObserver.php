<?php

namespace App\Observers;

use App\Events\WorkOrder\WorkOrderCompleted;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Events\WorkOrder\WorkOrderUpdated;
use App\Models\WorkOrder;

/**
 * Dispatches the WorkOrder domain events from the model lifecycle so they fire on
 * EVERY save path (admin UI, CSV import, ERP API, services) without each caller
 * remembering to. These events were previously defined but never dispatched.
 *
 * Separate from WorkOrderWebhookObserver (which drives outbound webhooks) — this
 * one feeds the module hook system (MenuRegistry-style extension points).
 */
class WorkOrderEventObserver
{
    public function created(WorkOrder $workOrder): void
    {
        event(new WorkOrderCreated($workOrder));
    }

    public function updated(WorkOrder $workOrder): void
    {
        event(new WorkOrderUpdated($workOrder, $workOrder->getChanges()));

        // First transition into DONE is the completion hook.
        if ($workOrder->wasChanged('status') && $workOrder->status === WorkOrder::STATUS_DONE) {
            event(new WorkOrderCompleted($workOrder));
        }
    }
}
