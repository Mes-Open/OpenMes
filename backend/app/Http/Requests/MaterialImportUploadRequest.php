<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaterialImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin');
    }

    public function rules(): array
    {
        return [
            'import_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:32768',
            'import_strategy' => 'required|in:update_or_create,skip_existing,create_only',
            'external_system' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
        ];
    }
}
