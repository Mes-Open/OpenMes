<?php

namespace App\Http\Requests\StepTime;

use App\Models\BatchStep;
use App\Models\BatchStepTimeEntry;
use App\Models\User;
use App\Rules\VisibleToCaller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A supervisor enters operator or machine time afterwards (#400): a forgotten
 * clock-on, or a machine without connectivity. Route-gated to Supervisor|Admin.
 */
class StoreManualStepTimeRequest extends FormRequest
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
            'kind' => ['required', 'string', Rule::in(BatchStepTimeEntry::KINDS)],
            'user_id' => ['exclude_unless:kind,labor', 'required', 'bail', 'integer', new VisibleToCaller(fn () => User::query())],
            'workstation_id' => ['exclude_unless:kind,machine', 'required', 'bail', 'integer', VisibleToCaller::workstation()],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
