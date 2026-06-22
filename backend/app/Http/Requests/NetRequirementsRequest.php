<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the MRP net-requirements API endpoint (#90).
 */
class NetRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth:sanctum + role middleware.
    }

    public function rules(): array
    {
        return [
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
