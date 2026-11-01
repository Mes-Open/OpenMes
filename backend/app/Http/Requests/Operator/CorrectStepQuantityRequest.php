<?php

namespace App\Http\Requests\Operator;

use App\Models\BatchStep;
use App\Rules\WholePieces;
use Illuminate\Foundation\Http\FormRequest;

class CorrectStepQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Operator', 'Supervisor', 'Admin']) ?? false;
    }

    public function rules(): array
    {
        $step = $this->route('batchStep');

        return [
            'good_qty' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2', new WholePieces($step instanceof BatchStep ? $step->batch?->workOrder?->productType : null)],
            'expected_good_qty' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'reason' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/'],
        ];
    }
}
