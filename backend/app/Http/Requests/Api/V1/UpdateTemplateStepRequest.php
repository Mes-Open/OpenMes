<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'instruction' => ['sometimes', 'nullable', 'string'],
            'estimated_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'setup_time_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'run_time_per_unit_minutes' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parameters' => ['sometimes', 'nullable', 'array'],
            'parameters.*' => ['nullable', 'string', 'max:1000'],
            'required_operators' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'workstation_id' => ['sometimes', 'nullable', 'integer', 'exists:workstations,id'],
            'workstation_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('workstation_types', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }
}
