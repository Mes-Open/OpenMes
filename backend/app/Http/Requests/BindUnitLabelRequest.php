<?php

namespace App\Http\Requests;

use App\Support\UnitSerialisation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Binding a unit's serial number to its process serial at a station. The
 * identifier formats and whether a process serial is required come from the
 * plant's settings (UnitSerialisation), not from the code.
 */
class BindUnitLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    protected function prepareForValidation(): void
    {
        $settings = app(UnitSerialisation::class);
        $this->merge([
            'serial_no' => $settings->normalize($this->input('serial_no')),
            'psn' => $settings->normalize($this->input('psn')),
        ]);
    }

    public function rules(): array
    {
        $settings = app(UnitSerialisation::class);

        return [
            'serial_no' => $settings->serialRules(),
            'psn' => $settings->psnRules(),
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
            // Re-binding a unit that already carries another process serial is a
            // supervisor's decision, with a reason for the unit's history.
            'force' => ['nullable', 'boolean'],
            'reason' => ['required_if:force,true', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'serial_no.regex' => __('The serial number does not match the configured format.'),
            'psn.regex' => __('The process serial number does not match the configured format.'),
            'psn.required' => __('A process serial number is required.'),
        ];
    }
}
