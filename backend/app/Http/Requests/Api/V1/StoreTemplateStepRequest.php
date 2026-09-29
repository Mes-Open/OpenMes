<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesEquipmentParameters;
use App\Models\TemplateStep;
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
            'kind' => ['nullable', Rule::in(TemplateStep::KINDS)],
            // Kind-specific configuration. Packing: what the station fills and how much fits.
            'config' => ['nullable', 'array'],
            'config.unit' => ['nullable', Rule::in(TemplateStep::PACKING_UNITS)],
            'config.carton_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'config.pallet_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'config.label_template_id' => ['nullable', 'integer', 'exists:label_templates,id'],
            'config.unit_label' => ['nullable', 'boolean'],
            'config.weight_expected_g' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'config.weight_tolerance_g' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
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
