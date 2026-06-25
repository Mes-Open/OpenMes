<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

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
            'required_operators' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'workstation_id' => ['sometimes', 'nullable', 'integer', 'exists:workstations,id'],
        ];
    }
}
