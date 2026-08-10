<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\WorkOrderStopType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Stopping production on a running work order (#182).
 */
class StopWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by the controller/policy.
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(WorkOrderStopType::values())],
            // A stop without a reason is the thing this feature replaces, so the
            // reason is required and not just recommended.
            'reason' => ['required', 'string', 'max:2000'],
            'batch_id' => [
                'nullable', 'integer',
                Rule::exists('batches', 'id')
                    ->where(fn ($q) => $q->where('work_order_id', $this->route('workOrder')?->id))
                    ->whereNull('deleted_at'),
            ],
            // Scoped to the routed order for the same reason as batch_id: an unscoped
            // exists() would let a caller link this stop to an issue raised on another
            // work order, and so on another tenant.
            'issue_id' => [
                'nullable', 'integer',
                Rule::exists('issues', 'id')
                    ->where(fn ($q) => $q->where('work_order_id', $this->route('workOrder')?->id))
                    ->whereNull('deleted_at'),
            ],
            // Supplying a downtime reason also opens a linked downtime record; leaving
            // it out records the stop only.
            'downtime_reason_id' => ['nullable', 'integer', Rule::exists('downtime_reasons', 'id')],
            'requires_change' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'A stop type is required.',
            'type.in' => 'The selected stop type is not supported.',
            'reason.required' => 'A reason for stopping production is required.',
            'batch_id.exists' => 'The selected batch does not belong to this work order.',
            'issue_id.exists' => 'The selected issue does not belong to this work order.',
        ];
    }
}
