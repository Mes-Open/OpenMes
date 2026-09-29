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

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator) {
            if (! is_string($this->input('production_flow_mode'))) {
                return;
            }
            $sources = app(\App\Services\Machine\MachineCountingCompatibility::class)->transitionBlockers($this->input('production_flow_mode'));
            if ($sources) {
                $validator->errors()->add('production_flow_mode', __('Resolve these requirements before changing production flow: :sources', ['sources' => implode(', ', $sources)]));
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'production_period' => 'required|in:none,weekly,monthly',
            'allow_overproduction' => 'nullable|boolean',
            'block_negative_stock' => 'sometimes|boolean',
            'telemetry_enabled' => 'sometimes|boolean',
            'force_sequential_steps' => 'nullable|boolean',
            'workstation_routing_enabled' => 'nullable|boolean',
            'backflush_on_pallet_creation' => 'nullable|boolean',
            'hold_on_material_shortage' => 'nullable|boolean',
            'pallet_stock_documents' => ['nullable', Rule::in(\App\Services\Warehouse\PalletStockDocumentService::MODES)],
            'lot_tracking_enabled' => 'nullable|boolean',
            'lot_picking_strategy' => 'nullable|in:fefo,fifo,lifo,manual',
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
            'production_flow_mode' => 'nullable|in:whole_batch,transfer',
            'cors_allowed_origins' => 'nullable|string|max:1000',
            'cors_allowed_methods' => 'nullable|string|max:200',
            'cors_max_age' => 'nullable|integer|min:0|max:86400',
            'production_qty_edit_policy' => 'required|in:none,timed,full',
            'production_qty_edit_window_minutes' => 'required_if:production_qty_edit_policy,timed|integer|min:1|max:60',
            'scanner_mode' => 'required|in:hid,manual',
            // Serialised units (UnitSerialisation) - patterns are bare regexes.
            'unit_identifier_normalize' => 'nullable|boolean',
            'unit_serial_pattern' => ['nullable', 'string', 'max:200', $this->validRegex()],
            'unit_psn_pattern' => ['nullable', 'string', 'max:200', $this->validRegex()],
            'unit_psn_required' => 'nullable|boolean',
            'unit_psn_unique' => 'nullable|boolean',
            'unit_test_fail_policy' => ['nullable', Rule::in(\App\Support\UnitSerialisation::FAIL_POLICIES)],
            'unit_test_max_attempts' => 'nullable|integer|min:1|max:99',
            'unit_test_attempts_scope' => ['nullable', Rule::in(\App\Support\UnitSerialisation::ATTEMPT_SCOPES)],
            'standard_weekly_hours' => 'nullable|numeric|min:1|max:168',
            'default_currency' => 'nullable|string|size:3',
            'default_pay_type' => 'nullable|in:hourly,weekly,piece_rate',
            'default_pay_rate' => 'nullable|numeric|min:0',
            // Optional feature modules (#144).
            'enabled_modules' => 'nullable|array',
            'enabled_modules.*' => ['string', Rule::in(ModuleRegistry::optionalKeys())],
        ];
    }

    /** A pattern the settings page accepts is one PCRE can compile. */
    private function validRegex(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! \App\Support\UnitSerialisation::validPattern((string) $value)) {
                $fail(__('The :attribute is not a valid regular expression.', ['attribute' => $attribute]));
            }
        };
    }
}
