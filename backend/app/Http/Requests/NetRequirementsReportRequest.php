<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the web MRP net-requirements report page (#90).
 */
class NetRequirementsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + tab-access middleware.
    }

    public function rules(): array
    {
        return [
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
