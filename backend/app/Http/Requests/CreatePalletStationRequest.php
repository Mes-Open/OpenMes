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
            'location' => ['nullable', 'string', 'max:100'],
        ];
    }
}
