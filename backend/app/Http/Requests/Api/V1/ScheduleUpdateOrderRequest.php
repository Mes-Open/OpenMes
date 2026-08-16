<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors the web planner's updateOrder rules (Web\Admin\SchedulePlannerController)
 * so a mobile drag is validated identically to a browser drag.
 */
class ScheduleUpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'line_id' => ['nullable', 'exists:lines,id'],
            'extra_placements' => ['sometimes', 'array', 'max:20'],
            'extra_placements.*.id' => ['nullable', 'integer'],
            'extra_placements.*.line_id' => ['required', 'exists:lines,id'],
            'extra_placements.*.due_date' => ['required', 'date'],
            'extra_placements.*.shift_number' => ['nullable', 'integer', 'min:1', 'max:10'],
            'extra_placements.*.end_date' => ['nullable', 'date', 'after_or_equal:extra_placements.*.due_date'],
            'extra_placements.*.end_shift_number' => ['nullable', 'integer', 'min:1', 'max:10'],
            'due_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:due_date'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:53'],
            'shift_number' => ['nullable', 'integer', 'min:1', 'max:10'],
            'end_shift_number' => ['nullable', 'integer', 'min:1', 'max:10'],
            'planned_start_at' => ['nullable', 'date'],
            'planned_end_at' => ['nullable', 'date', 'after:planned_start_at'],
            'force_conflict' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Only the placement keys the client actually sent — presence, not value,
     * decides what the service writes, so a partial edit can't null fields it
     * never meant to touch.
     */
    public function placementInput(): array
    {
        return $this->only([
            'line_id', 'due_date', 'week_number', 'shift_number', 'end_date',
            'end_shift_number', 'planned_start_at', 'planned_end_at', 'extra_placements',
        ]);
    }
}
