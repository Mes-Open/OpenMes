<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use App\Http\Requests\Concerns\ValidatesLineStockLocation;
use Illuminate\Foundation\Http\FormRequest;

class StoreLineRequest extends FormRequest
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

    /** A missing checkbox on create means "not filled in", not "unchecked". */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:lines,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'area_id' => ['nullable', 'exists:areas,id'],
            // The stock location this line's consumption comes off.
            'warehouse_id' => $this->stockLocationRules(),
            'is_active' => ['boolean'],
        ], $this->customFieldRules());
    }
}
