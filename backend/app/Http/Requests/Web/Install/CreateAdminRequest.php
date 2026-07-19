<?php

namespace App\Http\Requests\Web\Install;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin-creation step of the installer. `site_name`/`site_url` are optional in
 * unattended (INSTALLER_PRESET) installs — the preset supplies sensible
 * defaults — and required in the interactive wizard. The preset flag is read
 * from the install session set by the database step.
 */
class CreateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isPreset = (bool) (session('install_database_config')['preset'] ?? false);

        return [
            'admin_username' => 'required|string|max:255|unique:users,username',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            'site_name' => [$isPreset ? 'nullable' : 'required', 'string', 'max:255'],
            'site_url' => [$isPreset ? 'nullable' : 'required', 'url'],
        ];
    }
}
