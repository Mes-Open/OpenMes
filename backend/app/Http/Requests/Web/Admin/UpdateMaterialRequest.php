<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    use MergesCustomFieldRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function customFieldEntityType(): string
    {
        return 'material';
    }

    /**
     * Both columns are NOT NULL with DB defaults, but a cleared form field
     * arrives as null (ConvertEmptyStringsToNull) and would trip the constraint
     * on save. The store path coerces these in its request/controller; mirror it
     * here so editing-then-clearing doesn't 500. (DB defaults only apply when a
     * column is omitted, never when an explicit null is passed.)
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('default_scrap_percentage') === null || $this->input('default_scrap_percentage') === '') {
            $this->merge(['default_scrap_percentage' => 0]);
        }
        if ($this->input('unit_of_measure') === null || $this->input('unit_of_measure') === '') {
            $this->merge(['unit_of_measure' => 'pcs']);
        }
    }

    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:50', Rule::unique('materials', 'code')->ignore($this->route('material'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'material_type_id' => ['required', 'exists:material_types,id'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'tracking_type' => ['nullable', 'in:none,batch,serial'],
            'default_scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'external_code' => ['nullable', 'string', 'max:100'],
            'external_system' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->customFieldRules());
    }
}
