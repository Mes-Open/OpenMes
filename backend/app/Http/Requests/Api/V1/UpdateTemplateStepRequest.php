<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesEquipmentParameters;
use App\Models\TemplateStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateStepRequest extends FormRequest
{
    use ValidatesEquipmentParameters;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'kind' => ['sometimes', 'nullable', Rule::in(TemplateStep::KINDS)],
            // Kind-specific configuration. Packing: what the station fills and how much fits.
            'config' => ['sometimes', 'nullable', 'array'],
            'config.unit' => ['nullable', Rule::in(TemplateStep::PACKING_UNITS)],
            'config.carton_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'config.pallet_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'config.label_template_id' => ['nullable', 'integer', 'exists:label_templates,id'],
            'config.unit_label' => ['nullable', 'boolean'],
            'config.weight_expected_g' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'config.weight_tolerance_g' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'instruction' => ['sometimes', 'nullable', 'string'],
            'estimated_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'setup_time_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'run_time_per_unit_minutes' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parameters' => ['sometimes', 'nullable', 'array', self::keyValueMapRule()],
            'parameters.*' => ['nullable', 'string', 'max:1000'],
            'required_operators' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'workstation_id' => ['sometimes', 'nullable', 'integer', 'exists:workstations,id'],
            'workstation_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('workstation_types', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }
}
