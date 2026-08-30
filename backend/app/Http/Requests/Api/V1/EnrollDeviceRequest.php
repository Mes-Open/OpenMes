<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A self-enrolling sensor's first contact: it presents a one-time pairing code
 * and declares its own name + MAC address. Public (no auth) — the pairing code
 * is the authorization. Throttled at the route.
 */
class EnrollDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pairing_code' => ['required', 'string', 'max:64'],
            // A device label, not a person's name: letters, digits, spaces and
            // the usual separators are all legitimate.
            'name' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN][\pL\pN\s._\-]*$/u'],
            // Standard MAC-48, colon- or hyphen-separated (e.g. AA:BB:CC:DD:EE:FF).
            'mac_address' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/'],
        ];
    }
}
