<?php

namespace App\Http\Requests;

use App\Models\CrewBreakWindow;
use Illuminate\Foundation\Http\FormRequest;

class CrewBreakWindowRequest extends FormRequest
{
    /** Route middleware (role:Admin) gates access; allow the validated request through. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'crew_id' => 'required|exists:crews,id',
            'name' => 'required|string|max:120',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'days_of_week' => 'required|array|min:1',
            'days_of_week.*' => 'integer|between:1,7',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // Reject a window that overlaps another window for the same crew on a
            // shared weekday (excluding the row being edited).
            $ignoreId = $this->route('crew_break_window')?->id;
            $days = array_map('intval', $this->input('days_of_week', []));
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            $clash = CrewBreakWindow::query()
                ->where('crew_id', $this->input('crew_id'))
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->get()
                ->first(function (CrewBreakWindow $w) use ($days, $start, $end) {
                    $sharesDay = array_intersect($days, array_map('intval', $w->days_of_week ?? []));
                    if (empty($sharesDay)) {
                        return false;
                    }

                    // Time ranges overlap: start < other end AND end > other start.
                    return $start < substr((string) $w->end_time, 0, 5)
                        && $end > substr((string) $w->start_time, 0, 5);
                });

            if ($clash) {
                $v->errors()->add('start_time', __('This crew already has a break window that overlaps these times.'));
            }
        });
    }
}
