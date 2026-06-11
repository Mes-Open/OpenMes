<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerRequest extends FormRequest
{
    use MergesCustomFieldRules;

    public function authorize(): bool
    {
        // Route is gated by the admin role middleware.
        return true;
    }

    protected function customFieldEntityType(): string
    {
        return 'worker';
    }

    public function rules(): array
    {
        $workerId = $this->route('worker')?->id;

        return array_merge([
            'code' => ['required', 'string', 'max:50', Rule::unique('workers', 'code')->ignore($workerId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'crew_id' => ['nullable', 'exists:crews,id'],
            'wage_group_id' => ['nullable', 'exists:wage_groups,id'],
            'personnel_class_id' => ['nullable', 'exists:personnel_classes,id'],
            'pay_type' => ['nullable', Rule::in(Worker::PAY_TYPES)],
            'pay_rate' => ['nullable', 'numeric', 'min:0'],
            'pay_currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'skills' => ['nullable', 'array'],
            'skills.*.id' => ['required', 'exists:skills,id'],
            'skills.*.level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ], $this->customFieldRules());
    }
}
