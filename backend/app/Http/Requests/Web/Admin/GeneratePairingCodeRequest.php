<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\Workstation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin generates a one-time pairing code for a sensor to redeem. Optionally
 * pre-binds the line (and station) the enrolled device will feed. Admin-only via
 * the route's role middleware. The line/workstation are constrained to the
 * current tenant so a code cannot be bound to another tenant's resources.
 */
class GeneratePairingCodeRequest extends FormRequest
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
            'line_id' => [
                'nullable', 'integer',
                Rule::exists('lines', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            // Workstation has no tenant_id of its own — it is scoped through its
            // (tenant-scoped) Line, so validate via whereHas('line'), which the
            // Line global scope narrows to the current tenant.
            'workstation_id' => [
                'nullable', 'integer',
                function ($attribute, $value, $fail) {
                    if ($value !== null && ! Workstation::whereKey($value)->whereHas('line')->exists()) {
                        $fail(__('The selected workstation is invalid.'));
                    }
                },
            ],
        ];
    }
}
