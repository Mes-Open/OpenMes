<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShiftHandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'line_id' => ['nullable', 'exists:lines,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
