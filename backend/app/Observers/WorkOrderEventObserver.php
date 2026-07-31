<?php

namespace App\Observers;

use App\Events\WorkOrder\WorkOrderCompleted;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Events\WorkOrder\WorkOrderUpdated;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches the WorkOrder domain events from the model lifecycle so they fire on
 * EVERY save path (admin UI, CSV import, ERP API, services) without each caller
 * remembering to. These events were previously defined but never dispatched.
 *
 * Separate from WorkOrderWebhookObserver (which drives outbound webhooks) — this
 * one feeds the module hook system (MenuRegistry-style extension points). Every
 * dispatch is guarded so a throwing module listener can never break the core save.
 */
class WorkOrderEventObserver
{
    public function created(WorkOrder $workOrder): void
    {
        $this->fire(fn () => WorkOrderCreated::dispatch($workOrder));

        // A work order inserted already DONE (e.g. a historical CSV/ERP import) is
        // also a completion — mirror the transition path below.
        if ($workOrder->status === WorkOrder::STATUS_DONE) {
            $this->fire(fn () => WorkOrderCompleted::dispatch($workOrder));
        }
    }

    public function updated(WorkOrder $workOrder): void
    {
        $this->fire(fn () => WorkOrderUpdated::dispatch($workOrder, $workOrder->getChanges()));

        // First transition into DONE is the completion hook.
        if ($workOrder->wasChanged('status') && $workOrder->status === WorkOrder::STATUS_DONE) {
            $this->fire(fn () => WorkOrderCompleted::dispatch($workOrder));
        }
    }

    /** Dispatch a module hook event without ever breaking the underlying write. */
    private function fire(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (\Throwable $e) {
            Log::warning('WorkOrder hook failed', ['error' => $e->getMessage()]);
        }
    }
}
