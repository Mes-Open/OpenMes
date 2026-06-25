<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePalletStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'work_order_id' => ['required', 'exists:work_orders,id'],
            // Optional - the controller auto-links when the WO has one batch and
            // re-checks that a chosen batch belongs to the work order.
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'location' => ['nullable', 'string', 'max:100'],
        ];
    }
}
