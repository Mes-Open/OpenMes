<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + the admin tab-access middleware.
    }

    public function rules(): array
    {
        $divisionId = $this->route('division')->id;

        return [
            'factory_id' => ['required', 'exists:factories,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('divisions', 'code')->ignore($divisionId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }
}
