<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesEquipmentParameters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateStepRequest extends FormRequest
{
    use ValidatesEquipmentParameters;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step_number' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'instruction' => ['nullable', 'string'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'setup_time_minutes' => ['nullable', 'integer', 'min:0'],
            'run_time_per_unit_minutes' => ['nullable', 'numeric', 'min:0'],
            'parameters' => ['nullable', 'array', self::keyValueMapRule()],
            'parameters.*' => ['nullable', 'string', 'max:1000'],
            'required_operators' => ['nullable', 'integer', 'min:1'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'workstation_type_id' => ['nullable', 'integer', Rule::exists('workstation_types', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }
}
