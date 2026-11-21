<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Operator adds a produced quantity to a work order straight from the
 * workstation. Route middleware gates the operator area; the controller checks
 * the order belongs to the selected line and may be closed by a quantity.
 */
class RecordWorkOrderQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'produced_qty' => ['required', 'numeric', 'min:1'],
        ];
    }
}
