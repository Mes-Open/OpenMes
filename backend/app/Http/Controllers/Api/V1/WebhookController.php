<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookRequest;
use App\Models\Webhook;
use App\Support\WebhookEventRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Outgoing webhooks for the mobile/tablet app, mirroring the web admin webhooks
 * screen (Pages/admin/webhooks/Index.jsx + create/edit form): name, URL,
 * subscribed events, active state and last-triggered time. Full CRUD; the
 * signing secret is never read back (write-only, auto-generated when blank).
 */
class WebhookController extends Controller
{
    private function present(Webhook $w): array
    {
        return [
            'id' => $w->id,
            'name' => $w->name,
            'url' => $w->url,
            'events' => $w->events ?? [],
            'events_count' => is_array($w->events) ? count($w->events) : 0,
            'headers' => $w->headers ?? [],
            'is_active' => $w->is_active,
            'last_triggered_at' => optional($w->last_triggered_at)->toIso8601String(),
        ];
    }

    public function index(): JsonResponse
    {
        $webhooks = Webhook::orderBy('name')->get()->map(fn (Webhook $w) => $this->present($w));

        return response()->json(['data' => $webhooks]);
    }

    /** Event catalog for the create/edit form (key + human label). */
    public function eventTypes(): JsonResponse
    {
        return response()->json(['data' => WebhookEventRegistry::forForm()]);
    }

    public function store(WebhookRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['secret'] = $request->filled('secret') ? $request->input('secret') : Str::random(40);

        $webhook = Webhook::create($data);

        return response()->json(['data' => $this->present($webhook)], 201);
    }

    public function update(WebhookRequest $request, Webhook $webhook): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $webhook->is_active);
        // Only rotate the secret when a new one is explicitly provided.
        if (! $request->filled('secret')) {
            unset($data['secret']);
        }

        $webhook->update($data);

        return response()->json(['data' => $this->present($webhook->fresh())]);
    }

    public function toggleActive(Webhook $webhook): JsonResponse
    {
        $webhook->update(['is_active' => ! $webhook->is_active]);

        return response()->json(['data' => ['id' => $webhook->id, 'is_active' => $webhook->is_active]]);
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json(null, 204);
    }
}
