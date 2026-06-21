<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PerformQualityControlTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by the supervisor/admin middleware
    }

    public function rules(): array
    {
        return [
            'production_quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'samples' => ['required', 'array', 'min:1'],
            'samples.*.sample_number' => ['required', 'integer'],
            'samples.*.parameter_name' => ['required', 'string'],
            'samples.*.parameter_type' => ['required', 'in:measurement,pass_fail'],
            'samples.*.value_numeric' => ['nullable', 'numeric'],
            'samples.*.value_boolean' => ['nullable'],
            'samples.*.is_passed' => ['nullable'],
        ];
    }
}
