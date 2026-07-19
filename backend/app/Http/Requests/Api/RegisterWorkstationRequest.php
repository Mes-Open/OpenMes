<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a workstation client self-registration / heartbeat payload.
 *
 * The endpoint is unauthenticated by design (a freshly installed station only
 * knows the MAIN IP, no token) and lives on the trusted LAN; it is rate limited
 * at the route. The device_uuid is the client-generated stable identity.
 */
class RegisterWorkstationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            // Station name: letters, digits, spaces and - . _ (e.g. "Assembly-1").
            'name' => ['required', 'string', 'max:150', 'regex:/^[\p{L}\p{N} ._-]+$/u'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'ip_address' => ['nullable', 'ip'],
        ];
    }
}
