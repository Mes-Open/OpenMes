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
