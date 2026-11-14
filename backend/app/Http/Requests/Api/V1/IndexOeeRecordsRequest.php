<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\PaginatesOptionally;
use App\Rules\VisibleToCaller;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for GET /api/v1/oee.
 *
 * `line_id`, `date_from` and `date_to` are the original parameters. `line_id`
 * now has to be an integer (a non-numeric value used to reach Postgres and
 * fail with a 500) and is checked against the caller's tenant only.
 * Everything else is opt-in: without it the endpoint returns the legacy
 * response (newest 100 records, no `meta`).
 */
class IndexOeeRecordsRequest extends FormRequest
{
    use PaginatesOptionally;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Unknown ids are a 422, as they always were — but only ids the
            // caller could see count as known (no cross-tenant existence oracle).
            'line_id' => ['nullable', 'bail', 'integer', VisibleToCaller::line()],
            'workstation_id' => ['nullable', 'bail', 'integer', VisibleToCaller::workstation()],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'lean' => ['nullable', 'in:0,1,true,false'],
            ...$this->paginationRules(),
        ];
    }
}
