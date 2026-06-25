<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the optional WO-time lot picks submitted when starting a batch step.
 * Shape/existence only — business rules (lot belongs to material, released and
 * available, quantities sum to the required amount, negative-stock policy) run
 * inside LotPickingService::pickManualForAllocation, under row locks, so they
 * cannot race the stock state. The route is already behind role middleware and
 * the controller checks line ownership, so authorize() is open here.
 */
class StartStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'picks' => ['nullable', 'array'],
            'picks.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'picks.*.lots' => ['required', 'array', 'min:1'],
            'picks.*.lots.*.material_lot_id' => ['required', 'integer', 'exists:material_lots,id'],
            'picks.*.lots.*.picked_qty' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
