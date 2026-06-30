<?php

namespace App\Observers;

use App\Models\Batch;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\WebhookEventRegistry;

/**
 * Fires the batch.completed webhook event when a batch transitions to DONE (#20).
 */
class BatchWebhookObserver
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function updated(Batch $batch): void
    {
        if (! $batch->wasChanged('status') || $batch->status !== Batch::STATUS_DONE) {
            return;
        }

        $this->dispatcher->dispatch(WebhookEventRegistry::BATCH_COMPLETED, [
            'id' => $batch->id,
            'work_order_id' => $batch->work_order_id,
            'status' => $batch->status,
            'completed_at' => optional($batch->completed_at)->toIso8601String(),
        ]);
    }
}
