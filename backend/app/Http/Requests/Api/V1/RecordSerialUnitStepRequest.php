<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** One processing event on a serialised unit: where, what was measured, and the verdict. */
class RecordSerialUnitStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth:sanctum + role middleware.
    }

    public function rules(): array
    {
        return [
            'batch_step_id' => ['nullable', 'integer', 'exists:batch_steps,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'parameters' => ['nullable', 'array'],
            'result' => ['nullable', Rule::in(['pass', 'fail', 'rework'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
