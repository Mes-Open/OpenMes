<?php

namespace App\Http\Requests\Api\V1;

use App\Support\UnitSerialisation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A component scanned onto a serialised unit through the API. */
class BindUnitComponentApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth:sanctum + role middleware.
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['identifier' => app(UnitSerialisation::class)->normalize($this->input('identifier'))]);
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:100'],
            'material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')->whereNull('deleted_at')],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'batch_step_id' => ['nullable', 'integer', 'exists:batch_steps,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
        ];
    }
}
