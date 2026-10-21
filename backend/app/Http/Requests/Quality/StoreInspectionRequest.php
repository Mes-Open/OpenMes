<?php

namespace App\Http\Requests\Quality;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Start an inbound inspection. Shared by the API and the operator screen, so
 * both refuse the same people (InspectionPolicy::create) with the same rules.
 */
class StoreInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Inspection::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'lot_number' => ['required', 'string', 'max:100'],
            'quantity_received' => ['nullable', 'numeric', 'min:0'],
            'supplier_lot_ref' => ['nullable', 'string', 'max:100'],
            'source_container_no' => ['nullable', 'string', 'max:100'],
            'inspection_plan_id' => ['nullable', 'integer', 'exists:inspection_plans,id'],
        ];
    }
}
