<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkstationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:workstations,code'],
            'name' => ['required', 'string', 'max:255'],
            'workstation_type' => ['nullable', 'string', 'max:100'],
            'workstation_type_id' => ['nullable', 'integer', 'exists:workstation_types,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
