<?php

namespace App\Http\Requests\Web\Admin;

use App\Enums\CustomFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin routes are already role-gated.
        return true;
    }

    public function rules(): array
    {
        $optioned = in_array($this->input('type'), [
            CustomFieldType::Select->value,
            CustomFieldType::Multiselect->value,
        ], true);

        return [
            'entity_type' => ['required', 'string', Rule::in(array_keys(config('custom_fields.entities', [])))],
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', $this->uniqueKeyRule()],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'required' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],

            'config' => ['nullable', 'array'],
            // Option-based types need at least one {value,label} pair.
            'config.options' => array_filter([$optioned ? 'required' : 'nullable', 'array', $optioned ? 'min:1' : null]),
            'config.options.*.value' => ['required_with:config.options', 'string', 'max:191'],
            'config.options.*.label' => ['required_with:config.options', 'string', 'max:191'],
            'config.min' => ['nullable', 'numeric'],
            'config.max' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => __('The key must start with a letter and use only lowercase letters, numbers and underscores.'),
            'config.options.required' => __('Add at least one option for a dropdown or multi-select field.'),
        ];
    }

    /**
     * A key is unique per entity-type. (Stricter than the DB's per-tenant
     * uniqueness, which is fine while tenancy is dormant and avoids null-tenant
     * SQL pitfalls.) Overridden on update to ignore the current row.
     */
    protected function uniqueKeyRule(): \Illuminate\Validation\Rules\Unique
    {
        return Rule::unique('custom_field_definitions', 'key')
            ->where('entity_type', $this->input('entity_type'));
    }
}
