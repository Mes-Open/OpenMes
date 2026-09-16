<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_no' => 'required|string|max:100|unique:work_orders,order_no',
            'customer_order_no' => 'nullable|string|max:100',
            'line_id' => 'nullable|exists:lines,id',
            'product_type_id' => 'nullable|exists:product_types,id',
            'planned_qty' => 'required|numeric|min:0.01|max:99999999',
            'priority' => 'nullable|integer',
            'due_date' => 'nullable|date',
            'planned_start_at' => 'nullable|date',
            'description' => 'nullable|string',
            'extra_data' => 'nullable|array',
        ];
    }
}
