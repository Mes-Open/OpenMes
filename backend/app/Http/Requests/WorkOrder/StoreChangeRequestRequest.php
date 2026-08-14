<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\ChangeEffectivePoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Raising a production-change request (#182).
 *
 * The `proposed` block is validated field by field rather than accepted as free JSON:
 * it is what eventually gets written onto a live work order, so an unknown or
 * malformed key must fail here and not halfway through apply().
 */
class StoreChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by the controller/policy.
        return true;
    }

    public function rules(): array
    {
        $workOrderId = $this->route('workOrder')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:5000'],

            'proposed' => ['required', 'array', 'min:1'],
            'proposed.product_revision_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('product_revisions', 'id')->whereNull('deleted_at'),
            ],
            'proposed.planned_qty' => ['sometimes', 'numeric', 'min:0.01'],
            'proposed.line_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('lines', 'id')->whereNull('deleted_at'),
            ],
            'proposed.bom_template_ids' => ['sometimes', 'array'],
            'proposed.bom_template_ids.*' => [
                'integer',
                Rule::exists('process_templates', 'id')->whereNull('deleted_at'),
            ],
            'proposed.due_date' => ['sometimes', 'nullable', 'date'],
            'proposed.description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'proposed.production_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'effective_from' => ['sometimes', Rule::in(ChangeEffectivePoint::values())],
            'effective_from_batch_id' => [
                'nullable', 'integer',
                Rule::exists('batches', 'id')
                    ->where(fn ($q) => $q->where('work_order_id', $workOrderId))
                    ->whereNull('deleted_at'),
            ],
            'work_order_stop_id' => [
                'nullable', 'integer',
                Rule::exists('work_order_stops', 'id')
                    ->where(fn ($q) => $q->where('work_order_id', $workOrderId)),
            ],
            'produced_disposition' => ['nullable', 'string', 'max:2000'],
            'material_disposition' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'A change request title is required.',
            'reason.required' => 'A reason for the change is required.',
            'proposed.required' => 'A change request must propose at least one change.',
            'proposed.min' => 'A change request must propose at least one change.',
            'effective_from_batch_id.exists' => 'The selected batch does not belong to this work order.',
        ];
    }
}
