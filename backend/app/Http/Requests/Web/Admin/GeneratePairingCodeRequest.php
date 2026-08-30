<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin generates a one-time pairing code for a sensor to redeem. Optionally
 * pre-binds the line (and station) the enrolled device will feed. Admin-only via
 * the route's role middleware.
 */
class GeneratePairingCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
        ];
    }
}
