<?php

namespace App\Http\Requests;

use App\Support\ModuleRegistry;
use App\Support\TimezoneRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin-only system settings (Settings -> System).
 *
 * One ruleset for a form that spans several tabs: sections the user did not
 * touch simply are not submitted, which is why most fields are `nullable` and
 * why the controller writes each group only when it is present.
 */
class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already behind `auth` + `role:Admin`; repeated here so the
        // ruleset cannot be reused from an ungated route by accident.
        return $this->user()?->hasRole('Admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'production_period' => 'required|in:none,weekly,monthly',
            'allow_overproduction' => 'nullable|boolean',
            'force_sequential_steps' => 'nullable|boolean',
            'workstation_routing_enabled' => 'nullable|boolean',
            'backflush_on_pallet_creation' => 'nullable|boolean',
            'workflow_mode' => 'required|in:status,board_status',
            'pin_login_enabled' => 'nullable|boolean',
            // Single source of truth — the language switcher's configured locales.
            'language' => ['nullable', Rule::in(array_keys(config('app.available_locales', [])))],
            // Plant timezone. Persisted by TimezoneRegistry, not through the
            // controller's JSON map — it stores the identifier raw.
            'app_timezone' => ['nullable', 'string', Rule::in(TimezoneRegistry::identifiers())],
            'schedule_view_mode' => 'required|in:weekly,daily,monthly',
            'schedule_shifts_per_day' => 'required|integer|in:1,2,3,4',
            'schedule_horizon_weeks' => 'required|integer|min:1|max:52',
            'schedule_show_weekends' => 'nullable|boolean',
            'realtime_mode' => 'required|in:polling,off',
            'production_tracking_mode' => 'required|in:per_operation,cumulative,hybrid',
            'cors_allowed_origins' => 'nullable|string|max:1000',
            'cors_allowed_methods' => 'nullable|string|max:200',
            'cors_max_age' => 'nullable|integer|min:0|max:86400',
            'production_qty_edit_policy' => 'required|in:none,timed,full',
            'production_qty_edit_window_minutes' => 'required_if:production_qty_edit_policy,timed|integer|min:1|max:60',
            'scanner_mode' => 'required|in:hid,manual',
            'standard_weekly_hours' => 'nullable|numeric|min:1|max:168',
            'default_currency' => 'nullable|string|size:3',
            'default_pay_type' => 'nullable|in:hourly,weekly,piece_rate',
            'default_pay_rate' => 'nullable|numeric|min:0',
            // Optional feature modules (#144).
            'enabled_modules' => 'nullable|array',
            'enabled_modules.*' => ['string', Rule::in(ModuleRegistry::optionalKeys())],
        ];
    }
}
