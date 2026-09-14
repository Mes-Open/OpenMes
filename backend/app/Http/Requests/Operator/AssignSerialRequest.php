<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class AssignSerialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind role middleware; controller checks line ownership.
    }

    public function rules(): array
    {
        return [
            'serial_no' => ['nullable', 'string', 'max:100'],
            'auto_generate' => ['nullable', 'boolean'],
        ];
    }
}
