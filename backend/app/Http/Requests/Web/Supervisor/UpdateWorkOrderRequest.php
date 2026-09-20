<?php

namespace App\Http\Requests\Web\Supervisor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_no' => 'required|string|max:100|unique:work_orders,order_no,'.$this->route('workOrder')->id,
            'customer_order_no' => 'nullable|string|max:100',
            'customer_id' => ['nullable', \Illuminate\Validation\Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'line_id' => 'nullable|exists:lines,id',
            'product_type_id' => 'nullable|exists:product_types,id',
            'planned_qty' => 'required|numeric|min:0.01|max:99999999',
            'unit_price' => 'nullable|numeric|min:0|max:99999999',
            'priority' => 'nullable|integer|min:0|max:100',
            'due_date' => 'nullable|date',
            'planned_start_at' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
            'status' => 'required|in:PENDING,ACCEPTED,IN_PROGRESS,PAUSED,BLOCKED,DONE,REJECTED,CANCELLED',
        ];
    }
}
