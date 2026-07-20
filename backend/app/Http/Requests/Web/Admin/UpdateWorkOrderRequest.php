<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends FormRequest
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
            'order_no' => ['required', 'string', 'max:100', 'unique:work_orders,order_no,'.$this->route('work_order')->id],
            'customer_order_no' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'line_id' => ['nullable', 'exists:lines,id'],
            'product_type_id' => ['nullable', 'exists:product_types,id'],
            // Multi-BOM selection (which process templates back this order).
            // Only applied while the order has no batches - see the controller.
            // Each selected BOM must be a live template of the order's product type.
            'bom_template_ids' => ['nullable', 'array'],
            'bom_template_ids.*' => [
                'integer',
                Rule::exists('process_templates', 'id')
                    ->where('product_type_id', $this->input('product_type_id'))
                    ->whereNull('deleted_at'),
            ],
            'planned_qty' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'counting_source' => ['nullable', Rule::in(\App\Models\WorkOrder::COUNTING_SOURCES)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:PENDING,ACCEPTED,IN_PROGRESS,PAUSED,BLOCKED,DONE,REJECTED,CANCELLED'],
        ], $this->customFieldRules());
    }
}
