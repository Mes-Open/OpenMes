<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\ErpWebhookRequest;
use App\Models\ApiKey;
use App\Models\Webhook;
use App\Support\WebhookEventRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Self-service webhook registration for ERP integrations (T2a), authenticated
 * by API key with the `erp:webhooks:manage` scope.
 *
 * A key only ever sees the webhooks it created itself (`api_key_id`); those
 * an administrator or another key registered are a 404, indistinguishable
 * from a missing id. The signing secret is returned once, in the 201 of
 * store(), and never again. Disabling or deleting the key deactivates its
 * webhooks (ApiKey model events).
 */
class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $webhooks = Webhook::ownedBy($this->key($request))
            ->orderBy('name')
            ->get()
            ->map(fn (Webhook $w) => $this->present($w));

        return response()->json(['data' => $webhooks]);
    }

    public function show(Request $request, int $webhookId): JsonResponse
    {
        return response()->json(['data' => $this->present($this->find($request, $webhookId))]);
    }

    /** Event catalog: the keys a webhook may subscribe to, with labels. */
    public function eventTypes(): JsonResponse
    {
        return response()->json(['data' => WebhookEventRegistry::forForm()]);
    }

    public function store(ErpWebhookRequest $request): JsonResponse
    {
        $data = $request->validated();
        $secret = $request->filled('secret') ? (string) $request->input('secret') : Str::random(40);

        $webhook = Webhook::create([
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => $data['events'],
            'headers' => $data['headers'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'secret' => $secret,
            'api_key_id' => $this->key($request)->id,
        ]);

        // The only response that ever carries the secret — the integration
        // must store it now to verify X-OpenMES-Signature.
        return response()->json([
            'data' => $this->present($webhook),
            'secret' => $secret,
        ], 201);
    }

    public function update(ErpWebhookRequest $request, int $webhookId): JsonResponse
    {
        $webhook = $this->find($request, $webhookId);
        $data = $request->validated();

        $changes = array_intersect_key($data, array_flip(['name', 'url', 'events']));
        if ($request->has('headers')) {
            $changes['headers'] = $data['headers'] ?? null;
        }
        // Omitted = keep the current state; a PATCH must not silently re-enable.
        $changes['is_active'] = $request->boolean('is_active', $webhook->is_active);
        // Rotate the secret only when a new one is explicitly provided.
        if ($request->filled('secret')) {
            $changes['secret'] = (string) $request->input('secret');
        }

        $webhook->update($changes);

        return response()->json(['data' => $this->present($webhook->fresh())]);
    }

    public function destroy(Request $request, int $webhookId): JsonResponse
    {
        $this->find($request, $webhookId)->delete();

        return response()->json(null, 204);
    }

    private function key(Request $request): ApiKey
    {
        return $request->attributes->get('api_key');
    }

    /** The key's own webhook, or 404 — never another key's or an admin's. */
    private function find(Request $request, int $webhookId): Webhook
    {
        return Webhook::ownedBy($this->key($request))->findOrFail($webhookId);
    }

    /** @return array<string, mixed> never includes the secret */
    private function present(Webhook $w): array
    {
        return [
            'id' => $w->id,
            'name' => $w->name,
            'url' => $w->url,
            'events' => $w->events ?? [],
            'headers' => $w->headers ?? [],
            'is_active' => (bool) $w->is_active,
            'last_triggered_at' => $w->last_triggered_at?->toIso8601String(),
            'created_at' => $w->created_at?->toIso8601String(),
        ];
    }
}
