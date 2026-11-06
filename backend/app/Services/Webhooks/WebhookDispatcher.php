<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Scopes\TenantScope;
use App\Support\WebhookEventRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fans a domain event out to every active webhook subscribed to it (#20):
 * builds the payload, records a pending WebhookDelivery, and queues a
 * DeliverWebhookJob per endpoint.
 *
 * Tenant isolation: webhooks are matched on the tenant that owns the entity,
 * not on the ambient TenantScope. Events are often raised with no logged-in
 * user and no TenantContext (machine counting, MQTT, queue workers, artisan),
 * where TenantScope filters nothing — so callers pass the entity's tenant and
 * only that tenant's endpoints (or, for a null tenant, only tenant-less ones)
 * receive it.
 *
 * The payload is captured when the event happens, but the delivery row and the
 * job are created after the surrounding transaction commits, so a rolled-back
 * write never reaches a receiver.
 *
 * Best-effort by design — webhook plumbing must never break the business
 * action that triggered it, so all work is wrapped in a guard that swallows
 * (and logs) failures, including a missing table during install.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $data  the entity payload for this event
     * @param  int|null  $tenantId  tenant of the entity the event is about
     */
    public function dispatch(string $event, array $data, ?int $tenantId = null): void
    {
        if (! WebhookEventRegistry::exists($event)) {
            return;
        }

        $webhooks = $this->subscribers($event, $tenantId);
        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            DB::afterCommit(fn () => $this->enqueue($webhooks, $event, $payload));
        } catch (\Throwable $e) {
            Log::warning('Failed to schedule webhook deliveries', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Cheap pre-check so observers can skip building a payload (eager loads,
     * throttle bookkeeping) when nobody listens.
     */
    public function hasSubscribers(string $event, ?int $tenantId): bool
    {
        return WebhookEventRegistry::exists($event) && $this->subscribers($event, $tenantId)->isNotEmpty();
    }

    /** @return Collection<int, Webhook> */
    private function subscribers(string $event, ?int $tenantId): Collection
    {
        try {
            return Webhook::withoutGlobalScope(TenantScope::class)
                ->active()
                ->when(
                    $tenantId !== null,
                    fn (Builder $q) => $q->where('tenant_id', $tenantId),
                    fn (Builder $q) => $q->whereNull('tenant_id'),
                )
                ->get()
                ->filter(fn (Webhook $webhook) => $webhook->subscribesTo($event))
                ->values();
        } catch (\Throwable) {
            // DB/table not available (e.g. during install) — nothing to do.
            return collect();
        }
    }

    /**
     * @param  Collection<int, Webhook>  $webhooks
     * @param  array<string, mixed>  $payload
     */
    private function enqueue(Collection $webhooks, string $event, array $payload): void
    {
        foreach ($webhooks as $webhook) {
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
