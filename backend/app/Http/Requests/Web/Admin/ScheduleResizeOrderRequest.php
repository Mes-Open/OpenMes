<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleResizeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->filled('planned_start_at') && $this->filled('planned_end_at')) {
            return [
                'planned_start_at' => ['required', 'date'],
                'planned_end_at' => ['required', 'date', 'after:planned_start_at'],
            ];
        }
        if ($this->input('end_date') === null && $this->input('end_shift_number') === null) {
            return ['end_date' => ['nullable', 'date'], 'end_shift_number' => ['nullable', 'integer']];
        }
        $order = $this->route('workOrder');
        $start = ($order->planned_start_at ?? $order->due_date)?->format('Y-m-d') ?? 'today';

        return [
            'end_date' => ['required', 'date', 'after_or_equal:'.$start],
            'end_shift_number' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
