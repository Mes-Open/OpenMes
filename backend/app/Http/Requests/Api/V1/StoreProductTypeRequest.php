<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:product_types,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'shelf_life_value' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'shelf_life_unit' => ['nullable', 'required_with:shelf_life_value', 'in:days,months'],
        ];
    }
}
