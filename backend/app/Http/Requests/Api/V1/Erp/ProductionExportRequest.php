<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the production-completion export. All optional: an ERP typically
 * polls `?since=<last successful sync>` to fetch incremental changes, filters
 * by `status` (defaults to completed orders) and optionally a single `line`.
 * Authorization is handled by the auth.apikey + scope middleware.
 */
class ProductionExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(WorkOrder::STATUSES)],
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
