<?php

namespace App\Observers;

use App\Models\WorkOrder;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\WebhookEventRegistry;

/**
 * Fires the work_order.status_changed webhook event when a work order's
 * status column actually changes (#20).
 */
class WorkOrderWebhookObserver
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function updated(WorkOrder $workOrder): void
    {
        if (! $workOrder->wasChanged('status')) {
            return;
        }

        $this->dispatcher->dispatch(WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED, [
            'id' => $workOrder->id,
            'order_no' => $workOrder->order_no,
            'status' => $workOrder->status,
            'previous_status' => $workOrder->getOriginal('status'),
            'line_id' => $workOrder->line_id,
            'product_type_id' => $workOrder->product_type_id,
            'planned_qty' => $workOrder->planned_qty,
            'produced_qty' => $workOrder->produced_qty,
        ]);
    }
}
