<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Date-range filter for the web non-conformance report (#11).
 */
class NonConformanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + tab-access middleware.
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
