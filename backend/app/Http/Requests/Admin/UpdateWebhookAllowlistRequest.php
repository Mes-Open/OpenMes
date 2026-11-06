<?php

namespace App\Http\Requests\Admin;

use App\Rules\WebhookAllowlistEntry;
use App\Support\WebhookNetworkAllowlist;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Replace the webhook private-network allowlist (T2a). Administrators only —
 * the list decides which internal hosts the server will POST to, so it is not
 * delegated through the Webhooks tab permission. An empty list restores the
 * default (no private address reachable).
 */
class UpdateWebhookAllowlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('Admin');
    }

    protected function prepareForValidation(): void
    {
        $entries = $this->input('entries');

        // Trim each entry so " 10.0.0.0/8" and "10.0.0.0/8" are the same value
        // for `distinct`; blank lines from the textarea are dropped.
        if (is_array($entries)) {
            $this->merge([
                'entries' => array_values(array_filter(
                    array_map(fn ($e) => is_string($e) ? strtolower(trim($e)) : $e, $entries),
                    fn ($e) => $e !== '' && $e !== null,
                )),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'entries' => ['present', 'array', 'max:'.WebhookNetworkAllowlist::MAX_ENTRIES],
            'entries.*' => ['required', 'string', 'max:253', 'distinct', new WebhookAllowlistEntry],
        ];
    }

    public function messages(): array
    {
        return [
            'entries.*.distinct' => __('This entry is listed more than once.'),
            'entries.max' => __('At most :max entries are allowed.'),
        ];
    }
}
