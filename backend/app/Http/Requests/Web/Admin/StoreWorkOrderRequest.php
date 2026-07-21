<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderRequest extends FormRequest
{
    use MergesCustomFieldRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function customFieldEntityType(): string
    {
        return 'work_order';
    }

    public function rules(): array
    {
        return array_merge([
            'order_no' => ['required', 'string', 'max:100', 'unique:work_orders,order_no'],
            'customer_order_no' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'line_id' => ['nullable', 'exists:lines,id'],
            'product_type_id' => ['nullable', 'exists:product_types,id'],
            // Optional product revision (#180) — must belong to the order's
            // product type and be RELEASED (only released revisions produce).
            'product_revision_id' => [
                'nullable',
                Rule::exists('product_revisions', 'id')
                    ->where('product_type_id', $this->input('product_type_id'))
                    ->where('lifecycle_status', 'released')
                    ->whereNull('deleted_at'),
            ],
            // Optional multi-BOM selection: which process templates (BOMs) apply
            // to this order. Empty = auto-pick the single active template. Each
            // selected BOM must be a live template of the order's product type.
            'bom_template_ids' => ['nullable', 'array'],
            'bom_template_ids.*' => [
                'integer',
                Rule::exists('process_templates', 'id')
                    ->where('product_type_id', $this->input('product_type_id'))
                    ->whereNull('deleted_at'),
            ],
            'planned_qty' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], $this->customFieldRules());
    }
}
