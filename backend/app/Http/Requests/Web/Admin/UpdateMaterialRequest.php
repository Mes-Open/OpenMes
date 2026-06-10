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
