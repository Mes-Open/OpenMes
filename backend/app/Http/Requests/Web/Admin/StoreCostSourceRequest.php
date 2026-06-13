<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCostSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already restricts admin routes to the Admin role.
        return true;
    }

    /**
     * unit_cost, unit and currency are NOT NULL with DB defaults, but a cleared
     * form field arrives as null (ConvertEmptyStringsToNull) and would trip the
     * constraint on an explicit insert — the DB default only applies when the
     * column is omitted. Coerce a blank back to its default. is_active defaults
     * to true on create (a missing checkbox is unchecked, but new sources are
     * active by default).
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
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:cost_sources,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
