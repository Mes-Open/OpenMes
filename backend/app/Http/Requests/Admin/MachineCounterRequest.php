<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MachineCounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Supervisor']) ?? false;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'register' => ['source_type' => ['required', Rule::in(['tag', 'mapping'])], 'source_id' => ['required', 'integer', 'min:1']],
            'configure' => ['mode' => ['required', Rule::in(['cumulative', 'increment', 'pulse'])],
                'kind' => ['required', Rule::in(['good', 'reject', 'total'])], 'workstation_id' => ['required', 'integer'],
                'batch_step_id' => ['nullable', 'integer'], 'note' => ['required', 'string', 'max:1000']],
            'rebaseline', 'useLegacy' => ['note' => ['required', 'string', 'max:1000']],
            'review' => ['decision' => ['required', Rule::in(['apply', 'dismiss'])],
                'batch_step_id' => ['required_if:decision,apply', 'nullable', 'integer'], 'note' => ['required', 'string', 'max:1000']],
            'simulate' => ['value' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
                'event_id' => ['nullable', 'string', 'max:160'], 'timestamp' => ['nullable', 'date']],
            default => [],
        };
    }
}
