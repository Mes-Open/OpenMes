<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\PaginatesOptionally;
use App\Rules\VisibleToCaller;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for GET /api/v1/downtimes.
 *
 * `line_id` and `date` (a single day) are the original parameters. A line id
 * that matches nothing still yields an empty list rather than a 422, as it
 * always has. Everything else is opt-in: without it the endpoint returns the
 * legacy response (newest 50 downtimes, no `meta`).
 */
class IndexDowntimesRequest extends FormRequest
{
    use PaginatesOptionally;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'line_id' => ['nullable', 'integer'],
            'workstation_id' => ['nullable', 'bail', 'integer', VisibleToCaller::workstation()],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'active' => ['nullable', 'in:0,1,true,false'],
            'lean' => ['nullable', 'in:0,1,true,false'],
            ...$this->paginationRules(),
        ];
    }
}
