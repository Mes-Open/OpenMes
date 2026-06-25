<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\WebhookEventRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Fans a domain event out to every active webhook subscribed to it (#20):
 * builds the payload, records a pending WebhookDelivery, and queues a
 * DeliverWebhookJob per endpoint.
 *
 * Best-effort by design — webhook plumbing must never break the business
 * action that triggered it, so all work is wrapped in a guard that swallows
 * (and logs) failures, including a missing table during install.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $data  the entity payload for this event
     */
    public function dispatch(string $event, array $data): void
    {
        if (! WebhookEventRegistry::exists($event)) {
            return;
        }

        try {
            $webhooks = Webhook::query()->active()->get();
        } catch (\Throwable $e) {
            // DB/table not available (e.g. during install) — nothing to do.
            return;
        }

        $payload = [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        foreach ($webhooks as $webhook) {
            if (! $webhook->subscribesTo($event)) {
                continue;
            }

            $delivery = null;
            try {
                $delivery = WebhookDelivery::create([
                    'webhook_id' => $webhook->id,
                    'event_type' => $event,
                    'payload' => $payload,
                    'status' => WebhookDelivery::STATUS_PENDING,
                ]);

                DeliverWebhookJob::dispatch($delivery->id);
            } catch (\Throwable $e) {
                Log::warning('Failed to enqueue webhook delivery', [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);

                // Don't leave a pending delivery with no queued job behind it —
                // mark it failed so the admin log reflects reality.
                if ($delivery !== null) {
                    try {
                        $delivery->update([
                            'status' => WebhookDelivery::STATUS_FAILED,
                            'error' => mb_substr('Failed to enqueue: '.$e->getMessage(), 0, 500),
                        ]);
                    } catch (\Throwable) {
                        // best-effort
                    }
                }
            }
        }
    }
}
