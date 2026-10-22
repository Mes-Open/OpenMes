<?php

namespace App\Http\Requests\Quality;

use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * A chosen plan must be one the material may be inspected against — the
     * live, published version for it, its type, or a generic plan — never a
     * draft or another material's plan.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('inspection_plan_id')) {
                    return;
                }

                $material = Material::find($this->integer('material_id'));
                $applies = $material && InspectionPlan::applicableTo($material)
                    ->whereKey($this->integer('inspection_plan_id'))
                    ->exists();

                if (! $applies) {
                    $validator->errors()->add('inspection_plan_id', __('This inspection plan does not apply to the selected material.'));
                }
            },
        ];
    }
}
