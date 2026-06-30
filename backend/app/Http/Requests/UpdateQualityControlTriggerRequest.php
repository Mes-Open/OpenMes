<?php

namespace App\Http\Requests;

use App\Models\QualityControlTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQualityControlTriggerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by the admin tab-access middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'trigger_type' => ['required', Rule::in(QualityControlTrigger::TYPES)],
            'quality_check_template_id' => ['nullable', 'integer', 'exists:quality_check_templates,id'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'product_type_id' => ['nullable', 'integer', 'exists:product_types,id'],
            'threshold_n' => [
                Rule::requiredIf(fn () => in_array($this->input('trigger_type'), QualityControlTrigger::FREQUENCY_TYPES, true)),
                'nullable',
                'integer',
                'min:1',
            ],
            'downtime_min_minutes' => ['nullable', 'integer', 'min:0'],
            'is_blocking' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_blocking' => $this->boolean('is_blocking'),
            'is_active' => $this->boolean('is_active'),
        ]);

        // The form sends '' for the "Any"/"None" option on optional selects.
        foreach (['quality_check_template_id', 'line_id', 'workstation_id', 'product_type_id', 'threshold_n', 'downtime_min_minutes'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }
}
