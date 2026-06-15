<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + the admin tab-access middleware.
    }

    public function rules(): array
    {
        return [
            // divisions.factory_id is NOT NULL (a division always belongs to a
            // factory) — must be required, or the insert 500s on a fresh install
            // with no factory selected.
            'factory_id' => ['required', 'exists:factories,id'],
            'code' => ['required', 'string', 'max:50', 'unique:divisions,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }
}
