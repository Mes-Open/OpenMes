<?php

namespace App\Http\Requests\Operator;

use App\Http\Requests\Concerns\AllowsModuleFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Operator-side (web) step completion carrying the ISA-95 L3 actual times (#52)
 * posted by the confirm-actual-times modal. Route middleware gates the operator
 * area; the controller additionally checks the step belongs to the selected line.
 */
class CompleteBatchStepRequest extends FormRequest
{
    use AllowsModuleFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function moduleFieldFilter(): string
    {
        return 'validation.operator.step';
    }

    protected function moduleFieldContext(): array
    {
        // 'start' / 'complete' rather than the trait's create/edit wording:
        // both are POSTs, and which one it is, is the whole distinction here.
        return ['action' => 'complete', 'step' => $this->route('batchStep')];
    }

    public function rules(): array
    {
        return $this->withModuleFields([
            'actual_elapsed_minutes' => ['nullable', 'integer', 'min:0'],
            'actual_setup_minutes' => ['nullable', 'integer', 'min:0'],
            'actual_run_minutes' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
