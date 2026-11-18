<?php

namespace App\Observers;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Integration\ShipmentPresenter;
use App\Support\WebhookEventRegistry;

/**
 * Fires shipment.confirmed when a load is confirmed at the dock (#400). The
 * payload is the ERP shipment result (ShipmentPresenter), so the ERP can book
 * the delivery — loaded, missing, unexpected — without polling.
 */
class ShipmentWebhookObserver
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function updated(Shipment $shipment): void
    {
        if (! $shipment->wasChanged('status') || $shipment->status !== ShipmentStatus::Confirmed) {
            return;
        }

        $event = WebhookEventRegistry::SHIPMENT_CONFIRMED;
        $webhooks = $this->dispatcher->subscribersFor($event, $shipment->tenant_id);
        if ($webhooks->isEmpty()) {
            return;
        }

        $this->dispatcher->dispatch($event, ShipmentPresenter::present($shipment), $shipment->tenant_id, $webhooks);
    }
}
