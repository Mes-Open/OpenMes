<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the quality / non-conformance export. All optional: poll
 * `?since=<last sync>` for incremental changes, optionally narrow by issue
 * `status` or `line`. Authorization is handled by the auth.apikey + scope
 * middleware.
 */
class QualityExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in([
                Issue::STATUS_OPEN,
                Issue::STATUS_ACKNOWLEDGED,
                Issue::STATUS_RESOLVED,
                Issue::STATUS_CLOSED,
            ])],
            'line' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->input('limit', 100);
    }
}
