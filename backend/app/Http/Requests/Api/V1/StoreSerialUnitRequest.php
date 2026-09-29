<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SerialUnit;
use App\Support\UnitSerialisation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Registering a serialised unit through the API; identifier rules come from the plant's settings. */
class StoreSerialUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth:sanctum + role middleware.
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
            // A unit may be registered on its process serial alone; the SN follows.
            'serial_no' => ['required_without:psn', 'nullable', ...array_slice($settings->serialRules(), 1)],
            'psn' => array_merge($settings->psnRules(), $settings->get('unit_psn_unique') ? [Rule::unique('serial_units', 'psn')] : []),
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', Rule::in(SerialUnit::STATUSES)],
        ];
    }
}
