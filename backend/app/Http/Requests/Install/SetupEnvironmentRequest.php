<?php

namespace App\Http\Requests\Install;

use App\Support\TimezoneRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 1 of the installer: site name, URL and the plant timezone.
 */
class SetupEnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The whole install route group is gated by CheckInstallation, which
        // refuses once the installation is complete.
        return true;
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url'],
            // Checked against PHP's own identifier list rather than a free
            // string: this value ends up in date_default_timezone_set(), where
            // an unknown zone is a fatal error rather than a bad setting.
            'app_timezone' => ['required', 'string', Rule::in(TimezoneRegistry::identifiers())],
        ];
    }

    public function messages(): array
    {
        return [
            'app_timezone.required' => 'A plant timezone is required.',
            'app_timezone.in' => 'The selected timezone is not a known identifier.',
        ];
    }
}
