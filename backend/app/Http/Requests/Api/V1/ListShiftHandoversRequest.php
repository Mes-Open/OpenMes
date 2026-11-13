<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters of GET /api/v1/shift-handover.
 *
 * `line_id` keeps its original meaning (scopes the live balance only); the
 * `recent_*` / `business_date_*` parameters filter the `recent` handover list,
 * and `per_page` opts that list into pagination.
 */
class ListShiftHandoversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth:sanctum + role:Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'line_id' => ['nullable', 'integer'],
            // No `exists` rule: history of a since-deleted line must stay filterable;
            // an unknown id simply yields an empty list.
            'recent_line_id' => ['nullable', 'integer', 'min:1'],
            'business_date_from' => ['nullable', 'date_format:Y-m-d'],
            'business_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:business_date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
