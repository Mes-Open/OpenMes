<?php

namespace App\Http\Requests\Web\Admin\Schedule;

use App\Models\PlanningInsightAcknowledgement;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The board an insight belongs to: the planner view, its start date and line
 * filter, so the server recomputes the same insights the drawer shows. Base of
 * every Production Planner insight request.
 */
class PlanningInsightRequest extends FormRequest
{
    /** Keys look like `short:12:2026-11-24:1` — type, then ids and dates. */
    public const KEY_REGEX = '/^[a-z]+:[0-9]+(-[0-9]+)*(:[0-9]{4}-[0-9]{2}-[0-9]{2})?(:[0-9]+)?(:[0-9]{4}-[0-9]{2}-[0-9]{2})?$/';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PlanningInsightAcknowledgement::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'view_mode' => ['nullable', 'string', 'in:weekly,daily,hourly'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'line_id' => ['nullable', 'integer', 'min:1', 'exists:lines,id'],
        ];
    }

    /** @return array<string, mixed> */
    protected function insightKeyRules(): array
    {
        return ['insight_key' => ['required', 'string', 'max:191', 'regex:'.self::KEY_REGEX]];
    }
}
