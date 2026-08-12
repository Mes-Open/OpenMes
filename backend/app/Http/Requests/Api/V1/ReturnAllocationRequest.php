<?php

namespace App\Http\Requests\Api\V1;

use App\Models\MaterialAllocation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Return an unused quantity from a work-order material allocation back to stock
 * (#99). Authorized against the allocation's work order.
 */
class ReturnAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $allocation = $this->route('allocation');

        // A soft-deleted batch hides the relationship, so guard it before deref.
        return $allocation instanceof MaterialAllocation
            && $allocation->batch?->workOrder !== null
            && (bool) $this->user()?->can('view', $allocation->batch->workOrder);
    }

    public function rules(): array
    {
        return [
            'qty' => ['required', 'numeric', 'min:0.0001'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
