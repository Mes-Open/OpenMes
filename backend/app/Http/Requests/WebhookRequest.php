<?php

namespace App\Http\Requests;

use App\Rules\SafeWebhookUrl;
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
        $webhookId = $this->route('webhook')?->id;

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('webhooks', 'name')->whereNull('deleted_at')->ignore($webhookId),
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
}
