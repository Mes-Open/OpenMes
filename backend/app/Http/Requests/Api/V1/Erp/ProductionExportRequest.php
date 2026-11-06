<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Http\Requests\Api\V1\Erp\Concerns\ParsesErpIncludes;
use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the production-completion export. All optional: an ERP typically
 * polls `?since=<last successful sync>` to fetch incremental changes, filters
 * by `status` (defaults to completed orders) and optionally a single `line`.
 * `include` adds settlement sections per order; because those are heavier, the
 * page size is capped lower (max 100, default 50) when it is present.
 * Authorization is handled by the auth.apikey + scope middleware.
 */
class ProductionExportRequest extends FormRequest
{
    use ParsesErpIncludes;

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
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.($this->filled('include') ? 100 : 500)],
            'cursor' => ['nullable', 'string'],
            ...$this->includeRules(),
        ];
    }

    public function perPage(): int
    {
        return (int) $this->input('limit', $this->filled('include') ? 50 : 100);
    }
}
