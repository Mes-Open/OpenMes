<?php

namespace App\Http\Requests;

use App\Rules\SafeWebhookUrl;
use App\Support\TenantContext;
use App\Support\WebhookEventRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared store/update validation for outgoing webhook endpoints (#20).
 * Route is behind auth + the webhooks tab middleware.
 */
class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $webhookId = $this->ignoreId();

        return [
            'name' => [
                'required', 'string', 'max:150',
                // Names are unique per tenant among live rows (mirrors the
                // partial unique index on (COALESCE(tenant_id,0), name)).
                Rule::unique('webhooks', 'name')
                    ->where('tenant_id', $this->tenantId())
                    ->whereNull('deleted_at')
                    ->ignore($webhookId),
            ],
            'url' => ['required', 'string', 'max:2048', new SafeWebhookUrl],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(WebhookEventRegistry::keys())],
            // Optional signing secret; auto-generated when left blank on create.
            'secret' => ['nullable', 'string', 'min:16', 'max:255'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['nullable', 'string', 'max:1024'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** The webhook being edited, excluded from the unique-name check. */
    protected function ignoreId(): ?int
    {
        return $this->route('webhook')?->id;
    }

    /**
     * Tenant the name must be unique in: the signed-in user's, or — for an
     * API-key request, which has no user — the key's tenant from TenantContext.
     */
    protected function tenantId(): ?int
    {
        return $this->user()?->tenant_id ?? app(TenantContext::class)->id();
    }
}
