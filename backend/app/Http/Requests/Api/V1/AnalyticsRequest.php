<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\VisibleToCaller;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters shared by the `/api/v1/analytics/*` endpoints.
 *
 * Unvalidated, a non-numeric `days` threw a TypeError in Carbon and a
 * non-numeric `line_id` or malformed date reached Postgres — 500s, all of them.
 * `line_id` goes through the model so a line of another tenant behaves exactly
 * like one that does not exist.
 */
class AnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'line_id' => ['nullable', 'bail', 'integer', VisibleToCaller::line()],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function lineId(): ?int
    {
        return $this->filled('line_id') ? (int) $this->query('line_id') : null;
    }

    public function days(int $default = 30): int
    {
        return $this->filled('days') ? (int) $this->query('days') : $default;
    }
}
