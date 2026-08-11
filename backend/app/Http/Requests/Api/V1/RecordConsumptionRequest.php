<?php

namespace App\Http\Requests\Api\V1;

use App\Models\MaterialAllocation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Declare the actual (partial) consumed quantity for a work-order material
 * allocation (#99). Authorized against the allocation's work order, matching the
 * batch-step completion request.
 */
class RecordConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $allocation = $this->route('allocation');

        return $allocation instanceof MaterialAllocation
            && (bool) $this->user()?->can('view', $allocation->batch->workOrder);
    }

    public function rules(): array
    {
        return [
            'consumed_qty' => ['required', 'numeric', 'min:0'],
            'scrap_qty' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
