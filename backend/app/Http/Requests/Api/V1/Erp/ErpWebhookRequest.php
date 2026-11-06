<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Http\Requests\WebhookRequest;
use App\Models\ApiKey;
use App\Models\Webhook;
use Illuminate\Validation\Validator;

/**
 * Create/update of a webhook by an ERP integration API key (T2a). Same field
 * rules as the admin WebhookRequest (name unique per tenant, SSRF-safe URL,
 * known events, optional ≥16-char secret), plus what a machine credential
 * must not be able to do:
 *
 *  - set transport/hop-by-hop headers (`Host`, `Content-Length`, …) or forge
 *    OpenMES's own `X-OpenMES-*` signature headers;
 *  - register more than MAX_PER_KEY endpoints.
 *
 * PATCH is partial: omitted fields keep their stored value. Scope and key
 * authentication are enforced by the route middleware.
 */
class ErpWebhookRequest extends WebhookRequest
{
    /** Endpoints one API key may own — bounds what a leaked key can register. */
    public const MAX_PER_KEY = 25;

    private const FORBIDDEN_HEADERS = [
        'host', 'content-length', 'content-type', 'transfer-encoding', 'connection',
        'keep-alive', 'upgrade', 'te', 'trailer', 'proxy-authorization', 'proxy-connection',
    ];

    /**
     * Another key's (or an administrator's) webhook is a 404 before any field
     * is validated, so validation errors never confirm that it exists.
     */
    protected function prepareForValidation(): void
    {
        $id = $this->ignoreId();
        $key = $this->attributes->get('api_key');

        if ($id !== null && (! $key instanceof ApiKey || ! Webhook::ownedBy($key)->whereKey($id)->exists())) {
            abort(404);
        }
    }

    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->isMethod('PATCH')) {
            foreach (['name', 'url', 'events'] as $field) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        $rules['headers'][] = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_array($value)) {
                return;
            }
            foreach (array_keys($value) as $name) {
                $lower = strtolower(trim((string) $name));
                if (in_array($lower, self::FORBIDDEN_HEADERS, true) || str_starts_with($lower, 'x-openmes-')) {
                    $fail(__('The header ":name" cannot be set on a webhook.', ['name' => (string) $name]));

                    return;
                }
            }
        };

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->isMethod('POST')) {
                    return;
                }

                $key = $this->attributes->get('api_key');
                if ($key instanceof ApiKey
                    && Webhook::ownedBy($key)->count() >= self::MAX_PER_KEY) {
                    $validator->errors()->add('url', __('This API key has reached the maximum number of webhooks.'));
                }
            },
        ];
    }

    protected function ignoreId(): ?int
    {
        $id = $this->route('webhookId');

        return $id === null ? null : (int) $id;
    }
}
