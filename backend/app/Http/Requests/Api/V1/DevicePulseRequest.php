<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One sensor pulse. Authorization is handled by the auth.device middleware
 * (the device token); the body only carries the optional unit quantity for this
 * pulse (defaults to 1 — one unit left the station).
 */
class DevicePulseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            // Optional client-generated key so a retried pulse is applied once.
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }
}
