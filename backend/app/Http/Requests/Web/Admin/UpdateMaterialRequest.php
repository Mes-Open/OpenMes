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
     * The scrap % column is NOT NULL DEFAULT 0, but a cleared form field arrives
     * as null (ConvertEmptyStringsToNull) and would trip the constraint on save.
     * Coerce a blank back to 0.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('default_scrap_percentage') === null || $this->input('default_scrap_percentage') === '') {
            $this->merge(['default_scrap_percentage' => 0]);
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
