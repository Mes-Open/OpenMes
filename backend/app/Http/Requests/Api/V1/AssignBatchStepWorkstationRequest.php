<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BatchStep;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pool dispatch (#52): a supervisor assigns a specific workstation to a pending
 * step that carries only an Equipment Class. The route is role-gated
 * (Supervisor|Admin); here we additionally enforce the work-order policy so the
 * assignment cannot reach a step outside the caller's authorization scope.
 */
class AssignBatchStepWorkstationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $step = $this->route('batchStep');

        return $step instanceof BatchStep
            && (bool) $this->user()?->can('update', $step->batch->workOrder);
    }

    public function rules(): array
    {
        return [
            'workstation_id' => ['required', 'integer', 'exists:workstations,id'],
        ];
    }
}
