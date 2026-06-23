<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookRequest;
use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\WebhookEventRegistry;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WebhookController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/webhooks/Index', [
            'events' => WebhookEventRegistry::forForm(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/webhooks/Create', [
            'events' => WebhookEventRegistry::forForm(),
            'generatedSecret' => Str::random(40),
        ]);
    }

    public function store(WebhookRequest $request)
    {
        $data = $this->payload($request);
        $data['secret'] = $request->filled('secret') ? $request->input('secret') : Str::random(40);

        Webhook::create($data);

        return redirect()->route('admin.webhooks.index')
            ->with('success', __('Webhook created successfully.'));
    }

    public function edit(Webhook $webhook)
    {
        return Inertia::render('admin/webhooks/Edit', [
            // Never send the secret to the browser; only whether one is set.
            'webhook' => array_merge(
                $webhook->only('id', 'name', 'url', 'events', 'headers', 'is_active'),
                ['has_secret' => filled($webhook->secret)],
            ),
            'events' => WebhookEventRegistry::forForm(),
        ]);
    }

    public function update(WebhookRequest $request, Webhook $webhook)
    {
        $data = $this->payload($request);
        // Keep the existing secret unless a new one is explicitly provided.
        if ($request->filled('secret')) {
            $data['secret'] = $request->input('secret');
        }

        $webhook->update($data);

        return redirect()->route('admin.webhooks.index')
            ->with('success', __('Webhook updated successfully.'));
    }

    public function destroy(Webhook $webhook)
    {
        $webhook->delete();

        return redirect()->route('admin.webhooks.index')
            ->with('success', __('Webhook deleted successfully.'));
    }

    public function toggleActive(Webhook $webhook)
    {
        $webhook->update(['is_active' => ! $webhook->is_active]);

        $status = $webhook->is_active ? __('activated') : __('deactivated');

        return redirect()->route('admin.webhooks.index')
            ->with('success', __('Webhook :status successfully.', ['status' => $status]));
    }

    /** Read-only delivery log for a single endpoint. */
    public function deliveries(Webhook $webhook)
    {
        return Inertia::render('admin/webhooks/Deliveries', [
            'webhook' => $webhook->only('id', 'name', 'url'),
        ]);
    }

    /** Queue a one-off test delivery so the admin can verify the endpoint. */
    public function test(Webhook $webhook)
    {
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_type' => 'webhook.test',
            'payload' => [
                'event' => 'webhook.test',
                'data' => ['message' => 'This is a test delivery from OpenMES.'],
                'timestamp' => now()->toIso8601String(),
            ],
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        DeliverWebhookJob::dispatch($delivery->id);

        return redirect()->route('admin.webhooks.deliveries', $webhook)
            ->with('success', __('Test delivery queued.'));
    }

    /** @return array<string, mixed> */
    private function payload(WebhookRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'headers' => $validated['headers'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
