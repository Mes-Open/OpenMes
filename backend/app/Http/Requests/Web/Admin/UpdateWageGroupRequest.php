<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWageGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * base_hourly_rate and currency are NOT NULL with DB defaults, but a cleared
     * form field arrives as null (ConvertEmptyStringsToNull) and would trip the
     * constraint on save — the DB default only applies when the column is
     * omitted, never when an explicit null is passed. Coerce a blank back to its
     * default. On update a missing is_active checkbox means unchecked (false).
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('base_hourly_rate') === null || $this->input('base_hourly_rate') === '') {
            $this->merge(['base_hourly_rate' => 0]);
        }
        if ($this->input('currency') === null || $this->input('currency') === '') {
            $this->merge(['currency' => 'PLN']);
        }
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('wage_groups', 'code')->ignore($this->route('wage_group'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
