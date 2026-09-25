<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use App\Http\Requests\Concerns\ValidatesWorkforceIds;
use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerRequest extends FormRequest
{
    use MergesCustomFieldRules;
    use ValidatesWorkforceIds;

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
        $current = $this->route('worker');
        $workerId = $current?->id;

        return array_merge([
            'code' => ['required', 'string', 'max:50', Rule::unique('workers', 'code')->ignore($workerId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'crew_id' => ['nullable', 'integer', Rule::in($this->offeredWorkforceIds('crewOptions', $current?->crew_id))],
            'wage_group_id' => ['nullable', 'integer', Rule::in($this->offeredWorkforceIds('wageGroupOptions', $current?->wage_group_id))],
            'personnel_class_id' => ['nullable', 'integer', Rule::in($this->offeredWorkforceIds('personnelClassOptions', $current?->personnel_class_id))],
            'pay_type' => ['nullable', Rule::in(Worker::PAY_TYPES)],
            'pay_rate' => ['nullable', 'numeric', 'min:0'],
            'pay_currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'is_logistics' => ['boolean'],
            'skills' => ['nullable', 'array'],
            'skills.*.id' => ['required', 'integer', Rule::in($this->offeredWorkforceIds('skillOptions'))],
            'skills.*.level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ], $this->customFieldRules());
    }
}
