<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin-only quality targets (Settings → System → Production targets).
 *
 * Its own request rather than part of UpdateSystemSettingsRequest: that form
 * posts a whole tab set with many required fields, and saving two targets must
 * never be able to touch — or be blocked by — the rest of the settings.
 */
class UpdateProductionTargetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already behind `auth` + `role:Admin`; repeated so the
        // ruleset cannot be reused from an ungated route by accident.
        return $this->user()?->hasRole('Admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // "1,5" is how half of Europe types one and a half.
        foreach (['max_scrap_rate_pct', 'min_fpy_pct'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => str_replace(',', '.', trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'max_scrap_rate_pct' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,1'],
            'min_fpy_pct' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'max_scrap_rate_pct' => __('max scrap rate'),
            'min_fpy_pct' => __('min first-pass yield'),
        ];
    }

    public function messages(): array
    {
        return [
            'max_scrap_rate_pct.decimal' => __('The :attribute may have at most one decimal place.'),
            'min_fpy_pct.decimal' => __('The :attribute may have at most one decimal place.'),
        ];
    }
}
