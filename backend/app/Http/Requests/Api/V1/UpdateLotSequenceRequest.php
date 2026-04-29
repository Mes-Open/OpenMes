<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLotSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:50'],
            'product_type_id' => ['nullable', 'exists:product_types,id', Rule::unique('lot_sequences', 'product_type_id')->ignore($this->route('lotSequence'))],
            'prefix' => ['sometimes', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'pad_size' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'year_prefix' => ['sometimes', 'boolean'],
        ];
    }
}
