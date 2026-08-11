<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BatchStep;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Completing a batch step via the API, including the ISA-95 L3
 * operator-confirmed actual times (#52). The service enforces the
 * setup + run ≤ elapsed invariant; here we own authorization and shape.
 */
class CompleteBatchStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $step = $this->route('batchStep');

        return $step instanceof BatchStep
            && (bool) $this->user()?->can('view', $step->batch->workOrder);
    }

    public function rules(): array
    {
        return [
            'produced_qty' => ['nullable', 'numeric', 'min:0'],
            // ISA-95 L3 operator-confirmed actual times (#52).
            'actual_elapsed_minutes' => ['nullable', 'integer', 'min:0'],
            'actual_setup_minutes' => ['nullable', 'integer', 'min:0'],
            'actual_run_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
