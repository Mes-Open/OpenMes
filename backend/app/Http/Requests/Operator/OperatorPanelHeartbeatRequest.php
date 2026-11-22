<?php

namespace App\Http\Requests\Operator;

use App\Enums\WorkstationDeviceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Heartbeat the operator panel sends while an operator is logged in. The
 * route middleware gates the operator area; the user and IP address are
 * taken from the session and the request, never from the payload.
 */
class OperatorPanelHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Stable per-browser id the panel keeps in localStorage.
            'device_uuid' => ['required', 'string', 'max:56', 'regex:/^[A-Za-z0-9-]+$/'],
            'status' => ['required', Rule::enum(WorkstationDeviceStatus::class)],
            'line_id' => ['nullable', 'integer', Rule::exists('lines', 'id')->whereNull('deleted_at')],
            'workstation_id' => ['nullable', 'integer', Rule::exists('workstations', 'id')->whereNull('deleted_at')],
        ];
    }
}
