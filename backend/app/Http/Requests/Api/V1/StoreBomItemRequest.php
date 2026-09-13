<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBomItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templateId = $this->route('processTemplate')?->id ?? $this->route('process_template');

        return [
            'material_id' => [
                'required_without:product_type_id',
                'prohibits:product_type_id',
                'nullable',
                'exists:materials,id',
                Rule::unique('bom_items')->where(function ($query) use ($templateId) {
                    return $query->where('process_template_id', $templateId);
                }),
            ],
            'product_type_id' => ['required_without:material_id', 'nullable', 'exists:product_types,id', Rule::unique('bom_items', 'product_type_id')->where('process_template_id', $templateId)->whereNull('deleted_at')],
            'component_template_id' => ['nullable', 'exists:process_templates,id'],
            'template_step_id' => ['nullable', 'exists:template_steps,id'],
            'quantity_per_unit' => ['required', 'numeric', 'gt:0'],
            'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consumed_at' => ['nullable', 'string', 'in:start,during,end'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'extra_data' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'material_id.unique' => 'This material is already in the BOM for this template.',
        ];
    }
}
