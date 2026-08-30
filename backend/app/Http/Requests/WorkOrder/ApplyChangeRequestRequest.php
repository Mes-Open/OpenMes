<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\ChangeEffectivePoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Applying an approved change request (#182).
 *
 * The effective point may be confirmed or overridden at apply time — it is the last
 * decision made before the shop floor sees a new configuration, and the person
 * applying it is the one who knows what has run since approval. Whether the chosen
 * point is legal against what has already been executed is enforced by the service.
 */
class ApplyChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by the controller/policy.
        return true;
    }

    public function rules(): array
    {
        $workOrderId = $this->route('changeRequest')?->work_order_id;

        return [
            'effective_from' => ['sometimes', Rule::in(ChangeEffectivePoint::values())],
            'effective_from_batch_id' => [
                'nullable', 'integer',
                Rule::exists('batches', 'id')
                    ->where(fn ($q) => $q->where('work_order_id', $workOrderId))
                    ->whereNull('deleted_at'),
            ],
            'implementation_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
