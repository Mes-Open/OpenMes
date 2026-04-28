<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateStepRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'step_number' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'instruction' => ['nullable', 'string'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
        ];
    }
}
