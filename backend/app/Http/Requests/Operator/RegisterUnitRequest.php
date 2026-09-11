<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class RegisterUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind role middleware; controller checks line ownership.
    }

    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            // Omit to auto-generate from the product type's SerialSequence.
            'serial_no' => ['nullable', 'string', 'max:100'],
        ];
    }
}
