<?php

namespace App\Http\Requests\Api\V1;

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
            'customer_order_no' => 'nullable|string|max:100',
            'planned_qty' => 'nullable|numeric|min:0.01|max:99999999',
            'priority' => 'nullable|integer',
            'due_date' => 'nullable|date',
            'planned_start_at' => 'nullable|date',
            'description' => 'nullable|string',
        ];
    }
}
