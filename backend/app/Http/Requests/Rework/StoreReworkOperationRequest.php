<?php

namespace App\Http\Requests\Rework;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Send pieces of a non-conformance back to an earlier operation (#400).
 * Route-gated to Supervisor|Admin; here the issue's work order must also be
 * one the caller may change.
 */
class StoreReworkOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $issue = $this->route('issue');
        if (! $issue instanceof Issue || ! $this->user()) {
            return false;
        }

        return $issue->workOrder ? $this->user()->can('update', $issue->workOrder) : true;
    }

    public function rules(): array
    {
        $step = fn () => Rule::exists('batch_steps', 'id')->whereNull('deleted_at');

        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'reason' => ['required', 'string', 'max:2000'],
            'target_batch_step_id' => ['required', 'bail', 'integer', $step()],
            // Defaults to the issue's step.
            'source_batch_step_id' => ['nullable', 'bail', 'integer', $step()],
        ];
    }
}
