<?php

namespace App\Http\Requests\Web\Admin\Connectivity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an MQTT connection (create + update). Route middleware gates admin
 * access. The assignable line is constrained to the current tenant so a crafted
 * `line_id` cannot bind the device to another tenant's line (the guided picker is
 * already tenant-scoped; this enforces the same boundary server-side).
 */
class MqttConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'line_id' => [
                'nullable', 'integer',
                Rule::exists('lines', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'broker_host' => ['required', 'string', 'max:255'],
            'broker_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'client_id' => ['nullable', 'string', 'max:100'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'use_tls' => ['boolean'],
            'ca_cert' => ['nullable', 'string'],
            'keep_alive_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'qos_default' => ['required', 'integer', 'in:0,1,2'],
            'clean_session' => ['boolean'],
            'connect_timeout' => ['required', 'integer', 'min:1', 'max:120'],
            'reconnect_delay_seconds' => ['required', 'integer', 'min:1', 'max:300'],
        ];
    }
}
