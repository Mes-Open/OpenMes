<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Read whether a what-if schedule still matches the plant — the check the
 * Automatic scheduling page repeats when its tab regains focus. No input;
 * whoever may open the page (the route's tab gate) may ask.
 */
class ScheduleSimulationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [];
    }
}
