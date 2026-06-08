<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the admin inspection-plan form. `scope` is a form-only control
 * that decides which target FK is persisted (material / material_type / generic).
 */
class InspectionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth + role:Admin
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'scope' => ['required', 'string', 'in:material,material_type,generic'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'material_type_id' => ['nullable', 'integer', 'exists:material_types,id'],
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.name' => ['required', 'string', 'max:150'],
            'criteria.*.type' => ['required', 'string', 'in:visual,measurement,functional,pass_fail'],
            'criteria.*.required' => ['nullable', 'boolean'],
            'criteria.*.unit' => ['nullable', 'string', 'max:30'],
            'criteria.*.spec_min' => ['nullable', 'numeric'],
            'criteria.*.spec_max' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $scope = $this->input('scope');
            if ($scope === 'material' && ! $this->filled('material_id')) {
                $validator->errors()->add('material_id', __('Pick a material when scope is "material".'));
            }
            if ($scope === 'material_type' && ! $this->filled('material_type_id')) {
                $validator->errors()->add('material_type_id', __('Pick a material type when scope is "material type".'));
            }

            foreach ($this->input('criteria', []) as $i => $c) {
                if (($c['type'] ?? null) === 'measurement'
                    && isset($c['spec_min'], $c['spec_max'])
                    && $c['spec_min'] !== '' && $c['spec_max'] !== ''
                    && (float) $c['spec_min'] > (float) $c['spec_max']) {
                    $validator->errors()->add("criteria.$i.spec_min", __('Min cannot exceed max.'));
                }
            }
        });
    }

    /**
     * Normalized, persistable payload (scope resolved to FKs, criteria booleans
     * coerced). Does NOT decide draft/publish state — the controller does.
     */
    public function payload(): array
    {
        $data = $this->validated();
        $scope = $data['scope'];

        if ($scope === 'material') {
            $data['material_type_id'] = null;
        } elseif ($scope === 'material_type') {
            $data['material_id'] = null;
        } else {
            $data['material_id'] = null;
            $data['material_type_id'] = null;
        }
        unset($data['scope']);

        $data['criteria'] = array_map(function ($c) {
            $c['required'] = ! empty($c['required']);

            return $c;
        }, $data['criteria']);

        // is_active is owned by the publish lifecycle, never by the edit form.
        unset($data['is_active']);

        return $data;
    }
}
