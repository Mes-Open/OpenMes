<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBomItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_step_id' => ['nullable', 'exists:template_steps,id'],
            'quantity_per_unit' => ['sometimes', 'numeric', 'gt:0'],
            'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consumed_at' => ['sometimes', 'string', 'in:start,during,end'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'extra_data' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
