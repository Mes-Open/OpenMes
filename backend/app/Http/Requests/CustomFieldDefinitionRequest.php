<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create/update of a custom field definition for the mobile API.
 * Mirrors the web Admin Store/Update requests: entity + key + label + type,
 * with a per-entity unique key and an options list required for select types.
 * Authorization is the route's role:Admin middleware.
 */
class CustomFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $optioned = in_array($this->input('type'), [
            CustomFieldType::Select->value,
            CustomFieldType::Multiselect->value,
        ], true);

        $id = $this->route('customField')?->id;

        return [
            'entity_type' => ['required', 'string', Rule::in(array_keys(config('custom_fields.entities', [])))],
            'key' => [
                'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_field_definitions', 'key')
                    ->where('entity_type', $this->input('entity_type'))
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'required' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'config' => ['nullable', 'array'],
            'config.options' => array_filter([$optioned ? 'required' : 'nullable', 'array', $optioned ? 'min:1' : null]),
            'config.options.*.value' => ['required_with:config.options', 'string', 'max:191'],
            'config.options.*.label' => ['required_with:config.options', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => __('The key must start with a letter and use only lowercase letters, numbers and underscores.'),
            'config.options.required' => __('Add at least one option for a dropdown or multi-select field.'),
        ];
    }
}
