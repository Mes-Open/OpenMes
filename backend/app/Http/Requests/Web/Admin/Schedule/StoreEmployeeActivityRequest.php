<?php

namespace App\Http\Requests\Web\Admin\Schedule;

use App\Models\EmployeeActivity;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One block in a worker's day: what they do from when to when.
 *
 * A block whose end time is earlier than its start ends the next day — a
 * night shift 22:00–06:00 is entered as it is said. Equal times are refused:
 * they describe nothing, not a full day.
 */
class StoreEmployeeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route sits behind the admin schedule tab gate.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'integer', Rule::exists('workers', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::in(EmployeeActivity::TYPES)],
            'custom_code' => ['nullable', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'from_time' => ['required', 'date_format:H:i'],
            'to_time' => ['required', 'date_format:H:i', 'different:from_time'],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_time.different' => __('The end time must differ from the start time.'),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} start and end; the end moves to the next day when it is earlier */
    public function window(): array
    {
        $date = Carbon::parse($this->validated('date'));
        $start = $date->copy()->setTimeFromTimeString($this->validated('from_time'));
        $end = $date->copy()->setTimeFromTimeString($this->validated('to_time'));
        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }
}
