<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLineRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $lineId = $this->route('line')?->id;

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('lines', 'code')->ignore($lineId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'division_id' => ['sometimes', 'nullable', 'integer', 'exists:divisions,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
