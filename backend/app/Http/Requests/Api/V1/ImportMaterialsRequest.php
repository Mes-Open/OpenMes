<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ImportMaterialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_system' => ['required', 'string', 'max:50'],
            'materials' => ['required', 'array', 'min:1'],
            'materials.*.external_code' => ['required', 'string', 'max:100'],
            'materials.*.name' => ['required', 'string', 'max:255'],
            'materials.*.type' => ['nullable', 'string', 'exists:material_types,code'],
            'materials.*.unit' => ['nullable', 'string', 'max:20'],
            'materials.*.description' => ['nullable', 'string'],
            'materials.*.extra_data' => ['nullable', 'array'],
            'materials.*.ean' => ['nullable', 'string', 'max:20'],
            'materials.*.stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'materials.*.min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'materials.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'materials.*.price_currency' => ['nullable', 'string', 'max:3'],
            'materials.*.supplier_name' => ['nullable', 'string', 'max:255'],
            'materials.*.supplier_code' => ['nullable', 'string', 'max:100'],
        ];
    }
}
