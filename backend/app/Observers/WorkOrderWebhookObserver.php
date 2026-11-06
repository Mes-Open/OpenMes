<?php

namespace App\Observers;

use App\Models\WorkOrder;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Integration\WorkOrderPresenter;
use App\Support\WebhookEventRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * Fires the work_order.* webhook events (#20):
 *  - work_order.created when an order is created,
 *  - work_order.status_changed when the status column actually changes,
 *  - work_order.progress when produced_qty changes — at most once per order
 *    per `openmmes.webhooks.progress_throttle_seconds`, so a machine counter
 *    ticking every second can't flood the receiver. A dropped tick is not
 *    re-sent; the next progress or status event carries the current value.
 *
 * Payloads share WorkOrderPresenter with the ERP production export.
 */
class WorkOrderWebhookObserver
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function created(WorkOrder $workOrder): void
    {
        $event = WebhookEventRegistry::WORK_ORDER_CREATED;
        $webhooks = $this->dispatcher->subscribersFor($event, $workOrder->tenant_id);
        if ($webhooks->isEmpty()) {
            return;
        }

        $this->dispatcher->dispatch(
            $event,
            WorkOrderPresenter::webhookPayload($workOrder, null),
            $workOrder->tenant_id,
            $webhooks,
        );
    }

    public function updated(WorkOrder $workOrder): void
    {
        $statusChanged = $workOrder->wasChanged('status');
        $previousStatus = $statusChanged ? $workOrder->getOriginal('status') : null;

        if ($statusChanged) {
            $event = WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED;
            $webhooks = $this->dispatcher->subscribersFor($event, $workOrder->tenant_id);
            if ($webhooks->isNotEmpty()) {
                $this->dispatcher->dispatch(
                    $event,
                    WorkOrderPresenter::webhookPayload($workOrder, $previousStatus),
                    $workOrder->tenant_id,
                    $webhooks,
                );
            }
        }

        if ($workOrder->wasChanged('produced_qty')) {
            $this->progress($workOrder, $previousStatus);
        }
    }

    /** $previousStatus is null unless this same update also changed the status. */
    private function progress(WorkOrder $workOrder, ?string $previousStatus): void
    {
        $event = WebhookEventRegistry::WORK_ORDER_PROGRESS;

        // Subscriber check first, so the throttle window isn't consumed while
        // nobody listens (and nothing expensive runs per counter tick).
        $webhooks = $this->dispatcher->subscribersFor($event, $workOrder->tenant_id);
        if ($webhooks->isEmpty()) {
            return;
        }

        $seconds = (int) config('openmmes.webhooks.progress_throttle_seconds', 30);
        if ($seconds > 0 && ! Cache::add("webhooks:wo-progress:{$workOrder->id}", 1, $seconds)) {
            return;
        }

        $this->dispatcher->dispatch(
            $event,
            WorkOrderPresenter::webhookPayload($workOrder, $previousStatus)
                + ['previous_produced_qty' => (float) $workOrder->getOriginal('produced_qty')],
            $workOrder->tenant_id,
            $webhooks,
        );
    }
}
