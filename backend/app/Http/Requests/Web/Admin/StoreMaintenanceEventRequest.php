<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an "Add maintenance" placement on the planner. A defined maintenance
 * schedule can pre-fill it (must be active — the planner only offers active
 * schedules, so a direct POST cannot smuggle in an inactive one), or an ad-hoc
 * title/type is supplied instead. Admin access is gated by the route middleware.
 */
class StoreMaintenanceEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_id' => [
                'nullable', 'integer',
                Rule::exists('maintenance_schedules', 'id')->where('is_active', true),
            ],
            'title' => ['required_without:schedule_id', 'nullable', 'string', 'max:255'],
            'event_type' => ['nullable', 'in:planned,corrective,inspection'],
            'line_id' => ['required', 'integer', 'exists:lines,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'description' => ['nullable', 'string'],
        ];
    }
}
