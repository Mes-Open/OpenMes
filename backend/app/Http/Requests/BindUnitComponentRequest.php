<?php

namespace App\Http\Requests;

use App\Support\UnitSerialisation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Scanning a component onto a serialised unit. The component's identifier is
 * whatever its label says; which material it is may be chosen at the station
 * or left to what the identifier resolves to (a lot, a sub-assembly serial).
 */
class BindUnitComponentRequest extends FormRequest
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
            'identifier' => $settings->normalize($this->input('identifier')),
        ]);
    }

    public function rules(): array
    {
        return [
            // The unit by its SN or its PSN (a lookup, not a registration: the
            // formats were checked when the numbers were given).
            'serial_no' => ['required', 'string', 'max:100'],
            'identifier' => ['required', 'string', 'max:100'],
            'material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')->whereNull('deleted_at')],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'batch_step_id' => ['nullable', 'integer', 'exists:batch_steps,id'],
        ];
    }

    public function messages(): array
    {
        return ['serial_no.regex' => __('The serial number does not match the configured format.')];
    }
}
