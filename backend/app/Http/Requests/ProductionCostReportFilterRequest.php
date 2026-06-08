<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionCostReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is gated by the admin role middleware.
        return true;
    }

    /**
     * The from/to range only applies to the 'custom' preset; drop stale values
     * for any other preset so a leftover to<from pair can't trigger a false 422.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('preset') !== 'custom') {
            $this->merge(['from' => null, 'to' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'product_type_id' => ['nullable', 'integer', 'exists:product_types,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
