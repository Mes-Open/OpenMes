<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Putting a closed or open carton on a pallet. */
class AssignCartonPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return ['pallet_id' => ['required', 'integer', 'exists:pallets,id']];
    }
}
