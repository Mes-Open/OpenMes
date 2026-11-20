<?php

namespace App\Http\Requests\Schedule;

use App\Services\Schedule\Finite\SchedulingRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Generate a what-if finite-capacity schedule. Shared by the web page and the
 * API so both accept exactly the same parameters.
 */
class GenerateScheduleRequest extends FormRequest
{
    public const DEFAULT_HORIZON_DAYS = 28;

    public const MAX_HORIZON_DAYS = 90;

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['Admin', 'Supervisor']);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'respect_material' => $this->boolean('respect_material', true),
            'respect_workforce' => $this->boolean('respect_workforce', true),
            'respect_quality' => $this->boolean('respect_quality', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'start' => ['nullable', 'date'],
            'horizon_days' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_HORIZON_DAYS],
            'scope' => ['nullable', Rule::in(SchedulingRequest::SCOPES)],
            'respect_material' => ['boolean'],
            'respect_workforce' => ['boolean'],
            'respect_quality' => ['boolean'],
            'work_order_ids' => ['nullable', 'array', 'max:500'],
            'work_order_ids.*' => ['integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
        ];
    }

    /** The validated input as a scheduler request, with defaults filled in. */
    public function toSchedulingRequest(): SchedulingRequest
    {
        // Never schedule into the past: a start before now is moved up to now.
        $start = $this->filled('start') ? Carbon::parse($this->input('start')) : now();
        if ($start->lessThan(now())) {
            $start = now();
        }
        $start = $start->copy()->second(0);

        $days = (int) ($this->input('horizon_days') ?: self::DEFAULT_HORIZON_DAYS);

        return new SchedulingRequest(
            start: $start,
            end: $start->copy()->addDays($days),
            scope: $this->input('scope') ?: SchedulingRequest::SCOPE_UNPLANNED,
            respectMaterial: $this->boolean('respect_material'),
            workOrderIds: $this->filled('work_order_ids') ? array_map('intval', $this->input('work_order_ids')) : null,
            respectWorkforce: $this->boolean('respect_workforce'),
            respectQuality: $this->boolean('respect_quality'),
        );
    }
}
