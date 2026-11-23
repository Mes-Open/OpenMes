<?php

namespace App\Http\Requests\StepTime;

use App\Models\BatchStep;
use App\Rules\VisibleToCaller;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Clock on / off a step (labor) or start / stop its machine time (#400).
 * Anyone who may see the work order may record time on its running steps.
 */
class RecordStepTimeRequest extends FormRequest
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
            // Machine time only: the machine that runs the step, when it is not the step's own.
            'workstation_id' => ['nullable', 'bail', 'integer', VisibleToCaller::workstation()],
        ];
    }
}
