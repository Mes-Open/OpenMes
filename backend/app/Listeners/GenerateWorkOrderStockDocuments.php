<?php

namespace App\Listeners;

use App\Events\WorkOrder\WorkOrderCompleted;
use App\Services\Warehouse\WorkOrderStockDocumentService;
use App\Support\ModuleRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Creates the draft warehouse documents a completed work order implies (#212).
 *
 * Off by default for installs that do not run warehousing: it does nothing unless
 * the `warehouse` module is enabled, the auto-generation config flag is on, and
 * warehouses actually exist (WorkOrderStockDocumentService returns null with no
 * warehouse to post against). Never breaks the completion it reacts to — a
 * failure here must not roll back a finished work order.
 */
class GenerateWorkOrderStockDocuments
{
    public function __construct(private WorkOrderStockDocumentService $documents) {}

    public function handle(WorkOrderCompleted $event): void
    {
        if (! config('openmmes.warehouse.auto_documents', true)) {
            return;
        }

        try {
            if (! ModuleRegistry::isModuleEnabled('warehouse')) {
                return;
            }

            $this->documents->generateForCompletion($event->workOrder);
        } catch (\Throwable $e) {
            Log::warning('Could not generate stock documents for completed work order', [
                'work_order_id' => $event->workOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
