<?php

namespace App\Observers;

use App\Models\StockDocument;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Integration\StockDocumentPresenter;
use App\Support\WebhookEventRegistry;

/**
 * Fires stock_document.posted when a warehouse document becomes posted —
 * normally the draft → posted transition in StockDocumentService::post(), or a
 * document created already posted. Payload is the ERP stock-document export
 * shape (StockDocumentPresenter), so the ERP can book it without polling.
 */
class StockDocumentWebhookObserver
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function created(StockDocument $document): void
    {
        $this->firePosted($document);
    }

    public function updated(StockDocument $document): void
    {
        if ($document->wasChanged('status')) {
            $this->firePosted($document);
        }
    }

    private function firePosted(StockDocument $document): void
    {
        if ($document->status !== StockDocument::STATUS_POSTED) {
            return;
        }

        $event = WebhookEventRegistry::STOCK_DOCUMENT_POSTED;
        $webhooks = $this->dispatcher->subscribersFor($event, $document->tenant_id);
        if ($webhooks->isEmpty()) {
            return;
        }

        $document->loadMissing(['warehouse', 'workOrder', 'lines.material', 'lines.productType', 'lines.materialLot']);

        $this->dispatcher->dispatch($event, StockDocumentPresenter::present($document), $document->tenant_id, $webhooks);
    }
}
