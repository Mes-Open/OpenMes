<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin') ?? false;
    }

    public function rules(): array
    {
        $known = ['production_period' => 'in:none,weekly,monthly', 'allow_overproduction' => 'boolean',
            'workflow_mode' => 'in:status,board_status', 'force_sequential_steps' => 'boolean', 'production_flow_mode' => 'in:whole_batch,transfer', 'pin_login_enabled' => 'boolean'];
        $rule = $known[$this->route('key')] ?? null;

        return ['value' => $rule ? ['required', $rule] : ['present']];
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
