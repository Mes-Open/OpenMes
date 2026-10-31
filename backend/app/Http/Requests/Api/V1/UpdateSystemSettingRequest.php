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
    }

    public function rules(): array
    {
        $known = [CurrencyRegistry::SETTING_KEY => Rule::in(CurrencyRegistry::codes()), 'production_period' => 'in:none,weekly,monthly', 'allow_overproduction' => 'boolean',
            'workflow_mode' => 'in:status,board_status', 'force_sequential_steps' => 'boolean', 'production_flow_mode' => 'in:whole_batch,transfer', 'pin_login_enabled' => 'boolean'];
        $rule = $known[$this->route('key')] ?? null;

        return ['value' => $rule ? ['required', $rule] : ['present']];
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
