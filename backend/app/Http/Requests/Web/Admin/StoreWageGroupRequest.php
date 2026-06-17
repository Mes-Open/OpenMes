<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreWageGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already restricts admin routes to the Admin role.
        return true;
    }

    /**
     * base_hourly_rate and currency are NOT NULL with DB defaults, but a cleared
     * form field arrives as null (ConvertEmptyStringsToNull) and would trip the
     * constraint on an explicit insert — the DB default only applies when the
     * column is omitted. Coerce a blank back to its default. is_active defaults
     * to true on create.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('base_hourly_rate') === null || $this->input('base_hourly_rate') === '') {
            $this->merge(['base_hourly_rate' => 0]);
        }
        if ($this->input('currency') === null || $this->input('currency') === '') {
            $this->merge(['currency' => 'PLN']);
        }
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:wage_groups,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
