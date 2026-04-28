<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLineRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:lines,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
