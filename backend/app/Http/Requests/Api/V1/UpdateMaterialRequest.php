<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('materials', 'code')->ignore($this->route('material'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'material_type_id' => ['sometimes', 'exists:material_types,id'],
            'unit_of_measure' => ['sometimes', 'string', 'max:20'],
            'tracking_type' => ['sometimes', 'string', 'in:none,batch,serial'],
            'default_scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'extra_data' => ['nullable', 'array'],
            'external_code' => ['nullable', 'string', 'max:100'],
            'external_system' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
