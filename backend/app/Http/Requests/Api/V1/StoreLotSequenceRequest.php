<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLotSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'product_type_id' => ['nullable', 'exists:product_types,id', 'unique:lot_sequences,product_type_id'],
            'prefix' => ['required', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'pad_size' => ['nullable', 'integer', 'min:1', 'max:10'],
            'year_prefix' => ['nullable', 'boolean'],
        ];
    }
}
