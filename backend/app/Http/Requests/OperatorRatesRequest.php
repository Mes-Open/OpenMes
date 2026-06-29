<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the operator production-rate analytics endpoint. Validates the
 * query params (ids, window) so malformed input returns 422 instead of a 500 or
 * being silently coerced. Route is already role-gated (Supervisor|Admin).
 */
class OperatorRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operator_id' => ['nullable', 'integer', 'exists:users,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
