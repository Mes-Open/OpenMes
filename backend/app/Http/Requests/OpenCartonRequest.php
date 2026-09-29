<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Opening a carton at packing - for a work order, on a pallet, or bare. */
class OpenCartonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
        ];
    }
}
