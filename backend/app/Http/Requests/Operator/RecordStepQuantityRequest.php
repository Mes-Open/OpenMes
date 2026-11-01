<?php

namespace App\Http\Requests\Operator;

use App\Models\BatchStep;
use App\Models\ProductType;
use App\Rules\WholePieces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Operator logs pieces leaving a batch step: good ones move on to the next
 * station, scrapped ones are recorded as a bare number (reason added later).
 * Route middleware gates the operator area; the controller checks the step
 * belongs to the selected line and the service checks what is actually waiting.
 */
class RecordStepQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'good_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999', new WholePieces($this->productType())],
            'scrap_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999', new WholePieces($this->productType())],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Pass the good pieces on through the following steps owned by the
            // same workstation (a station doing several steps in a row). Only
            // transfer flow acts on it; whole-batch flow logs the step alone.
            'through_station' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ((float) $this->input('good_qty', 0) + (float) $this->input('scrap_qty', 0) <= 0) {
                $v->errors()->add('good_qty', __('Log at least one good or scrapped piece.'));
            }
        });
    }

    /** The product the step's order makes — decides whether fractions are allowed. */
    private function productType(): ?ProductType
    {
        $step = $this->route('batchStep');

        return $step instanceof BatchStep ? $step->batch?->workOrder?->productType : null;
    }

    public function goodQty(): float
    {
        return (float) ($this->validated()['good_qty'] ?? 0);
    }

    public function throughStation(): bool
    {
        return (bool) ($this->validated()['through_station'] ?? false);
    }

    public function scrapQty(): float
    {
        return (float) ($this->validated()['scrap_qty'] ?? 0);
    }
}
