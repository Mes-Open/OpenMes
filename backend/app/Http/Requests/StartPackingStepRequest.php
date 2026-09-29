<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The packaging lots an operator picked before packing starts: per material, one or more lots with quantities. */
class StartPackingStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'picks' => ['nullable', 'array'],
            'picks.*' => ['array'],
            'picks.*.*.material_lot_id' => ['required', 'integer', 'exists:material_lots,id'],
            'picks.*.*.picked_qty' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
