<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Operator records a work order's production quantity against a shift.
 * Route middleware gates the operator area; the controller checks the order
 * belongs to the selected line and may be closed by a quantity.
 */
class RecordShiftQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'exists:shifts,id'],
            'quantity' => ['required', 'numeric', 'min:1'],
        ];
    }
}
