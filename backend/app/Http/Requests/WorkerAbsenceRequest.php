<?php

namespace App\Http\Requests;

use App\Models\WorkerAbsence;
use Illuminate\Foundation\Http\FormRequest;

class WorkerAbsenceRequest extends FormRequest
{
    /** Route middleware (role:Admin) gates access; allow the validated request through. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_id' => 'required|exists:workers,id',
            'type' => 'required|in:'.implode(',', WorkerAbsence::TYPES),
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'all_day' => 'boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'status' => 'nullable|in:'.implode(',', WorkerAbsence::STATUSES),
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // Reject an approved absence that overlaps an existing one for the
            // same worker (excluding the row being edited).
            $ignoreId = $this->route('worker_absence')?->id;

            $overlaps = WorkerAbsence::query()
                ->approved()
                ->where('worker_id', $this->input('worker_id'))
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->overlapping($this->date('starts_on')->toDateString(), $this->date('ends_on')->toDateString())
                ->exists();

            if ($overlaps && ($this->input('status', 'approved') === 'approved')) {
                $v->errors()->add('starts_on', __('This worker already has an absence that overlaps these dates.'));
            }
        });
    }
}
