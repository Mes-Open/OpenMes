<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaterialImportProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin');
    }

    public function rules(): array
    {
        return [
            'file_path' => 'required|string|max:255',
            'import_strategy' => 'required|in:update_or_create,skip_existing,create_only',
            'mapping' => 'required|array|min:1',
            'mapping.*' => 'nullable|string|max:50',
            'external_system' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_]*$/'],
        ];
    }
}
