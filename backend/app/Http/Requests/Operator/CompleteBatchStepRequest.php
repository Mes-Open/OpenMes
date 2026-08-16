<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Operator-side (web) step completion carrying the ISA-95 L3 actual times (#52)
 * posted by the confirm-actual-times modal. Route middleware gates the operator
 * area; the controller additionally checks the step belongs to the selected line.
 */
class CompleteBatchStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_elapsed_minutes' => ['nullable', 'integer', 'min:0'],
            'actual_setup_minutes' => ['nullable', 'integer', 'min:0'],
            'actual_run_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
