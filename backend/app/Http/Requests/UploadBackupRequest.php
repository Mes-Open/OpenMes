<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadBackupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Defense in depth: route is role:Admin gated, but an uploaded backup
        // is later read by the restore flow, so enforce admin here too.
        return $this->user()?->hasRole('Admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:512000'], // max 500MB
        ];
    }
}
