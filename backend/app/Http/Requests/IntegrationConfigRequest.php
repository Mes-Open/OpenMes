<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create/update of an external-system integration. Mirrors the web
 * Admin\IntegrationConfigController rules. Authorization is the route's
 * role:Admin middleware; the encrypted api_config is managed elsewhere.
 */
class IntegrationConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('integration')?->id;

        return [
            'system_type' => [
                'required', 'string', 'max:50',
                Rule::unique('integration_configs', 'system_type')->ignore($id),
            ],
            'system_name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
