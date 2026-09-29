<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a line to a routing's BOM. A line is exactly one of material /
 * product type: `required_without` + `prohibits` enforce exactly-one;
 * `component_kind` (sent by the UI) is ignored so older material-only callers
 * keep working.
 */
class StoreBomItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the admin route group already requires the role
    }

    public function rules(): array
    {
        $template = $this->route('process_template');

        return [
            'material_id' => [
                'required_without:product_type_id',
                'prohibits:product_type_id',
                'nullable',
                'exists:materials,id',
                Rule::unique('bom_items', 'material_id')
                    ->where('process_template_id', $template->id)
                    ->whereNull('deleted_at'),
            ],
            'product_type_id' => [
                'required_without:material_id',
                'nullable',
                'exists:product_types,id',
                // A product can't be a component of itself.
                Rule::notIn([$template->product_type_id]),
                Rule::unique('bom_items', 'product_type_id')
                    ->where('process_template_id', $template->id)
                    ->whereNull('deleted_at'),
            ],
            'template_step_id' => 'nullable|exists:template_steps,id',
            'quantity_per_unit' => 'required|numeric|gt:0',
            'per' => 'nullable|in:unit,carton,pallet',
            'scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'consumed_at' => 'nullable|in:start,during,end',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'material_id.unique' => __('This material is already in the BOM for this template.'),
            'product_type_id.unique' => __('This product type is already in the BOM for this template.'),
            'product_type_id.not_in' => __('A product type cannot be a component of itself.'),
        ];
    }
}
