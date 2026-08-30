<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use App\Http\Requests\Concerns\ValidatesLineStockLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLineRequest extends FormRequest
{
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
    }

    public function rules(): array
    {
        return array_merge([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('lines', 'code')->ignore($this->route('line')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'area_id' => ['nullable', 'exists:areas,id'],
            // The stock location this line's consumption comes off.
            'warehouse_id' => $this->stockLocationRules(),
            'is_active' => ['boolean'],
        ], $this->customFieldRules());
    }
}
