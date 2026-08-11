<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\ChangeEffectivePoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a draft production-change request (#182).
 *
 * Same field rules as raising one, but every key is optional — a caller may fix just
 * the title. Whether the request is still editable at all is a domain rule enforced by
 * the service, not by validation.
 */
class UpdateChangeRequestRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'reason' => ['sometimes', 'string', 'max:5000'],

            'proposed' => ['sometimes', 'array', 'min:1'],
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
            'produced_disposition' => ['nullable', 'string', 'max:2000'],
            'material_disposition' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'proposed.min' => 'A change request must propose at least one change.',
            'effective_from_batch_id.exists' => 'The selected batch does not belong to this work order.',
        ];
    }
}
