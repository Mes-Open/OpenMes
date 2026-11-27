<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Requests\Concerns\AllowsModuleFields;
use App\Http\Requests\Concerns\MergesCustomFieldRules;
use App\Http\Requests\Concerns\ValidatesLineStockLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLineRequest extends FormRequest
{
    use AllowsModuleFields;
    use MergesCustomFieldRules;
    use ValidatesLineStockLocation;

    public function authorize(): bool
    {
        // Route middleware already restricts admin routes to the Admin role.
        return true;
    }

    protected function customFieldEntityType(): string
    {
        return 'line';
    }

    /** On edit an unticked checkbox really does mean "inactive". */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);

        // A cleared efficiency field means "no derating", not "no capacity".
        if ($this->exists('planning_efficiency_pct') && blank($this->input('planning_efficiency_pct'))) {
            $this->merge(['planning_efficiency_pct' => 100]);
        }
    }

    public function rules(): array
    {
        return $this->withModuleFields(array_merge([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('lines', 'code')->ignore($this->route('line')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // The stock location this line's consumption comes off.
            'warehouse_id' => $this->stockLocationRules(),
            'is_active' => ['boolean'],
            // Share of calendar capacity planning may load (100 = all of it).
            'planning_efficiency_pct' => ['nullable', 'numeric', 'min:1', 'max:100'],
        ], $this->customFieldRules()));
    }

    /** Fields an installed module adds to the line form (e.g. the plant area). */
    protected function moduleFieldFilter(): string
    {
        return 'validation.admin.lines';
    }

    protected function moduleFieldContext(): array
    {
        return ['action' => $this->moduleFieldAction(), 'line' => $this->route('line')];
    }
}
