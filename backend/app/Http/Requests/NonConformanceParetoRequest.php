<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Date-range filter for the non-conformance Pareto API endpoint (#11).
 */
class NonConformanceParetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth:sanctum + role middleware.
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
