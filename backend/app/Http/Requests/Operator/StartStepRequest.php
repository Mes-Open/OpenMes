<?php

namespace App\Http\Requests\Operator;

use App\Http\Requests\Concerns\AllowsModuleFields;
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
        return ['action' => 'start', 'step' => $this->route('batchStep')];
    }

    public function rules(): array
    {
        return $this->withModuleFields([
            'picks' => ['nullable', 'array'],
            'picks.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'picks.*.lots' => ['required', 'array', 'min:1'],
            'picks.*.lots.*.material_lot_id' => ['required', 'integer', 'exists:material_lots,id'],
            'picks.*.lots.*.picked_qty' => ['required', 'numeric', 'gt:0'],
        ]);
    }
}
