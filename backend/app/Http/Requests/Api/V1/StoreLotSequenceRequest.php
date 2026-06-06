<?php

namespace App\Http\Requests\Api\V1;

use App\Models\LotSequence;
use App\Rules\ValidLotPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'pattern' => ['nullable', 'string', 'max:100', new ValidLotPattern],
            'prefix' => ['required_without:pattern', 'nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'pad_size' => ['nullable', 'integer', 'min:1', 'max:10'],
            'year_prefix' => ['nullable', 'boolean'],
            'reset_period' => ['nullable', Rule::in(LotSequence::RESET_PERIODS)],
        ];
    }
}
