<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Support\WebhookUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Delivers one webhook payload over HTTP with an HMAC-SHA256 signature (#20).
 *
 * Retries on transport failure / non-2xx with exponential backoff; the
 * WebhookDelivery row is updated in place each attempt, and failed() records
 * the terminal failure once retries are exhausted.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** Cap the response body we persist, to keep the log table lean. */
    private const MAX_RESPONSE_BODY = 2000;

    public function __construct(public int $deliveryId) {}

    /** Exponential-ish backoff between retries (seconds). */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 900];
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if (! $delivery || $delivery->status === WebhookDelivery::STATUS_SUCCESS) {
            return;
        }

        $webhook = $delivery->webhook()->withTrashed()->first();
        if (! $webhook || $webhook->trashed() || ! $webhook->is_active) {
            $this->markFailed($delivery, 'Webhook is no longer active.');

            return;
        }

        // Re-validate at delivery time AND capture the vetted IP to pin the
        // connection to (below), so DNS can't rebind to an internal address
        // between this check and the actual connect. Unsafe = terminal.
        try {
            $target = WebhookUrlGuard::safeTarget($webhook->url);
        } catch (Throwable $e) {
            $this->markFailed($delivery, $e->getMessage());
            $this->fail($e);

            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, $webhook->secret);

        $headers = array_merge(
            is_array($webhook->headers) ? $webhook->headers : [],
            [
                'Content-Type' => 'application/json',
                'X-OpenMES-Event' => $delivery->event_type,
                'X-OpenMES-Delivery' => (string) $delivery->id,
                'X-OpenMES-Signature' => 'sha256='.$signature,
            ],
        );

        $delivery->attempts = $delivery->attempts + 1;

        try {
            $response = Http::withHeaders($headers)
                // Pin the socket to the IP we just vetted (Host header + TLS SNI
                // still use the original hostname) — closes the rebinding window.
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]]])
                ->timeout(10)
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (Throwable $e) {
            $delivery->error = $e->getMessage();
            $delivery->save();
            throw $e; // retry with backoff
        }

        $delivery->response_code = $response->status();
        $delivery->response_body = mb_substr((string) $response->body(), 0, self::MAX_RESPONSE_BODY);

        if ($response->successful()) {
            $delivery->status = WebhookDelivery::STATUS_SUCCESS;
            $delivery->error = null;
            $delivery->delivered_at = now();
            $delivery->save();

            $webhook->forceFill(['last_triggered_at' => now()])->saveQuietly();

            return;
        }

        $delivery->error = "Endpoint returned HTTP {$response->status()}.";
        $delivery->save();

        // Non-2xx → retry with backoff (or land in failed() when exhausted).
        throw new \RuntimeException($delivery->error);
    }

    public function failed(?Throwable $e): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if ($delivery && $delivery->status !== WebhookDelivery::STATUS_SUCCESS) {
            $this->markFailed($delivery, $e?->getMessage() ?? 'Delivery failed.');
        }
    }

    private function markFailed(WebhookDelivery $delivery, string $error): void
    {
        $delivery->status = WebhookDelivery::STATUS_FAILED;
        $delivery->error = mb_substr($error, 0, 500);
        $delivery->save();
    }
}
