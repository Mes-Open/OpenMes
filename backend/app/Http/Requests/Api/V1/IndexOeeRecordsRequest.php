<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\PaginatesOptionally;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for GET /api/v1/oee.
 *
 * `line_id`, `date_from` and `date_to` are the original parameters and keep
 * their original rules. Everything else is opt-in: without it the endpoint
 * returns the legacy response (newest 100 records, no `meta`).
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
            'line_id' => ['nullable', 'exists:lines,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'lean' => ['nullable', 'in:0,1,true,false'],
            ...$this->paginationRules(),
        ];
    }
}
