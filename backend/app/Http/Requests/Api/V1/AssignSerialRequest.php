<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssignSerialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth:sanctum.
    }

    public function rules(): array
    {
        return [
            'serial_no' => ['nullable', 'string', 'max:100'],
            'auto_generate' => ['nullable', 'boolean'],
        ];
    }
}
