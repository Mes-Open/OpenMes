<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCostSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * unit_cost, unit and currency are NOT NULL with DB defaults, but a cleared
     * form field arrives as null (ConvertEmptyStringsToNull) and would trip the
     * constraint on save — the DB default only applies when the column is
     * omitted, never when an explicit null is passed. Coerce a blank back to its
     * default. On update a missing is_active checkbox means unchecked (false).
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('unit_cost') === null || $this->input('unit_cost') === '') {
            $this->merge(['unit_cost' => 0]);
        }
        if ($this->input('unit') === null || $this->input('unit') === '') {
            $this->merge(['unit' => 'szt']);
        }
        if ($this->input('currency') === null || $this->input('currency') === '') {
            $this->merge(['currency' => 'PLN']);
        }
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('cost_sources', 'code')->ignore($this->route('cost_source'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
