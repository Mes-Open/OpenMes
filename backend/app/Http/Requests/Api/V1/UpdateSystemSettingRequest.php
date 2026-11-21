<?php

namespace App\Http\Requests\Api\V1;

use App\Support\CurrencyRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('key') === CurrencyRegistry::SETTING_KEY && is_string($this->input('value'))) {
            $this->merge(['value' => CurrencyRegistry::normalise($this->input('value'))]);
        }

        // The same clean-up as Settings → System, so the stored value is the
        // one the header needs (browsers match methods case-sensitively).
        $normalise = [
            'cors_allowed_origins' => \App\Rules\CorsOrigins::normalise(...),
            'cors_allowed_methods' => \App\Rules\CorsMethods::normalise(...),
        ][$this->route('key')] ?? null;
        if ($normalise && is_string($this->input('value'))) {
            $this->merge(['value' => $normalise($this->input('value'))]);
        }
    }

    public function rules(): array
    {
        $known = [CurrencyRegistry::SETTING_KEY => Rule::in(CurrencyRegistry::codes()), 'production_period' => 'in:none,weekly,monthly', 'allow_overproduction' => 'boolean',
            'workflow_mode' => 'in:status,board_status', 'force_sequential_steps' => 'boolean', 'production_flow_mode' => 'in:whole_batch,transfer', 'pin_login_enabled' => 'boolean',
            // Settings → System → Production, with the settings form's own rules.
            'production_tracking_mode' => 'in:per_operation,cumulative,hybrid', 'production_qty_edit_policy' => 'in:none,timed,full',
            'production_qty_edit_window_minutes' => ['integer', 'min:1', 'max:60'], 'scanner_mode' => 'in:hid,manual',
            'workstation_routing_enabled' => 'boolean', 'default_pay_type' => 'in:hourly,weekly,piece_rate',
            'standard_weekly_hours' => ['numeric', 'min:1', 'max:168']];
        // The CORS keys go straight into response headers, so the API checks
        // them exactly as Settings → System does.
        $cors = [
            'cors_allowed_origins' => ['present', 'nullable', 'string', 'max:1000', new \App\Rules\CorsOrigins],
            'cors_allowed_methods' => ['present', 'nullable', 'string', 'max:200', new \App\Rules\CorsMethods],
            'cors_max_age' => ['required', 'integer', 'min:0', 'max:86400'],
        ];
        if (isset($cors[$this->route('key')])) {
            return ['value' => $cors[$this->route('key')]];
        }
        // Blank means "no default rate", as on the settings form.
        if ($this->route('key') === 'default_pay_rate') {
            return ['value' => ['present', 'nullable', 'numeric', 'min:0']];
        }

        $rule = $known[$this->route('key')] ?? null;

        return ['value' => $rule ? ['required', ...(is_array($rule) ? $rule : [$rule])] : ['present']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->route('key') === CurrencyRegistry::SETTING_KEY
            ? ['value.in' => __('The selected currency is not a supported ISO 4217 code.')]
            : [];
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator) {
            if ($this->route('key') !== 'production_flow_mode' || ! is_string($this->input('value'))) {
                return;
            }
            $blockers = app(\App\Services\Machine\MachineCountingCompatibility::class)->transitionBlockers($this->input('value'));
            if ($blockers) {
                $validator->errors()->add('value', __('Resolve these requirements before changing production flow: :sources', ['sources' => implode(', ', $blockers)]));
            }
        }];
    }
}
