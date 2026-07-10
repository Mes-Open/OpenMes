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
            'planned_qty' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], $this->customFieldRules());
    }
}
