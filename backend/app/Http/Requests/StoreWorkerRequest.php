<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AllowsModuleFields;
use App\Http\Requests\Concerns\MergesCustomFieldRules;
use App\Http\Requests\Concerns\ValidatesWorkforceIds;
use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkerRequest extends FormRequest
{
    use AllowsModuleFields;
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

    protected function moduleFieldFilter(): string
    {
        return 'validation.admin.workers';
    }

    protected function moduleFieldContext(): array
    {
        return ['action' => $this->moduleFieldAction(), 'worker' => null];
    }

    public function rules(): array
    {
        // The record being edited may hold a value the pickers no longer
        // offer — crewOptions() serves only active crews. Keeping its own
        // value valid stops an unrelated edit from failing on it.
        $current = null;

        return $this->withModuleFields(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:workers,code'],
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
        ], $this->customFieldRules()));
    }
}
