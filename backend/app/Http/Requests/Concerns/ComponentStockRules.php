<?php

namespace App\Http\Requests\Concerns;

class ComponentStockRules
{
    public static function messages(): array
    {
        return [
            'required' => __('The :attribute field is required.'),
            'date' => __('The :attribute field must be a valid date and time.'),
            'after' => __('The :attribute field must be after :date.'),
            'numeric' => __('The :attribute field must be a number.'),
            'integer' => __('The :attribute field must be an integer.'),
            'exists' => __('The selected :attribute is invalid.'),
            'array' => __('The :attribute field must be a list.'),
        ];
    }

    public static function attributes(): array
    {
        return [
            'planned_qty' => __('Planned Qty'), 'product_type_id' => __('Product Type'),
            'planned_start_at' => __('Planned start'), 'planned_end_at' => __('Planned end'),
            'component_warehouse_ids' => __('Component warehouses'), 'component_warehouse_ids.*' => __('Component warehouse'),
        ];
    }

    public static function rules(string $prefix = ''): array
    {
        $rules = [
            'use_component_stock' => ['nullable', 'boolean'],
            'component_warehouse_ids' => ['nullable', 'array', 'max:100'],
            'component_warehouse_ids.*' => ['integer', 'distinct', \Illuminate\Validation\Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'excluded_component_paths' => ['nullable', 'array', 'max:1000'],
            'excluded_component_paths.*' => ['string', 'max:500', 'distinct'],
            'component_preview_token' => ['nullable', 'string', 'size:64'],
            'planned_start_at' => ['nullable', 'date'],
            'planned_end_at' => ['nullable', 'date', 'after:'.$prefix.'planned_start_at'],
        ];

        return collect($rules)->mapWithKeys(fn ($value, $key) => [$prefix.$key => $value])->all();
    }
}
