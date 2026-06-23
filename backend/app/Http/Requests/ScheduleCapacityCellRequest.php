<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the schedule-capacity cell drill-down query. The /admin/schedule
 * prefix's tab.access middleware gates authorization; this only validates input.
 */
class ScheduleCapacityCellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'line_id' => 'required|integer|exists:lines,id',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ];
    }
}
