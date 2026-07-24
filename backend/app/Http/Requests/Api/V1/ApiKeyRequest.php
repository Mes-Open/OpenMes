<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ApiScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create/update of an ERP integration API key. The secret itself is
 * generated server-side and never accepted from the client, so only metadata
 * (name, scopes, IP allowlist, expiry, active state, linked integration) is
 * validated here. Authorization is the route's role:Admin middleware.
 */
class ApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'integration_config_id' => [
                'nullable',
                Rule::exists('integration_configs', 'id')->whereNull('deleted_at'),
            ],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => [Rule::in(ApiScope::values())],
            'ip_allowlist' => ['nullable', 'array'],
            'ip_allowlist.*' => ['ip'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
