<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Minute-level move/resize for the mobile planner. Unlike the web mirror this
 * requires both timestamps: the legacy shift-level span branch is web-only.
 */
class ScheduleResizeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'planned_start_at' => ['required', 'date'],
            'planned_end_at' => ['required', 'date', 'after:planned_start_at'],
            'force_conflict' => ['nullable', 'boolean'],
        ];
    }
}
