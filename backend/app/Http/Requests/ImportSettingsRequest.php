<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A configuration file for Settings → System → Import (admins only, by the route). */
class ImportSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind role:Admin.
    }

    public function rules(): array
    {
        return ['settings_file' => ['required', 'file', 'mimes:json,txt', 'max:10240']];
    }
}
