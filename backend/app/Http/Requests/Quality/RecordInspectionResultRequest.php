<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Record the measured value of one criterion (InspectionPolicy::recordResult).
 */
class RecordInspectionResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordResult', $this->route('inspection')) ?? false;
    }

    public function rules(): array
    {
        return [
            'value_numeric' => ['nullable', 'numeric'],
            'value_boolean' => ['nullable', 'boolean'],
            'value_text' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
