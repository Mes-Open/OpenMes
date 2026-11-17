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
 *
 * `status` is one status (`?status=DONE`), a list (`?status[]=DONE&status[]=IN_PROGRESS`)
 * or `all`, so an integration that tracks every status needs one request per
 * cycle instead of one per status. `include` adds settlement sections per order;
 * because those are heavier, the page size is capped lower (max 100, default 50)
 * when it is present. Authorization is handled by the auth.apikey + scope
 * middleware.
 */
class ProductionExportRequest extends FormRequest
{
    use ParsesErpIncludes;

    /** `?status=all` — no status filter at all. */
    public const STATUS_ALL = 'all';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * A single status is folded into a one-element list, so the rules below
     * only have to describe the list form. `all` is kept as-is: it is a mode,
     * not a status, and is not accepted inside a list.
     */
    protected function prepareForValidation(): void
    {
        $status = $this->input('status');

        if (is_string($status) && $status !== '' && $status !== self::STATUS_ALL) {
            $this->merge(['status' => [$status]]);
        }
    }

    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'status' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if (! is_array($value) && $value !== self::STATUS_ALL) {
                    $fail(__('The :attribute must be a work-order status, a list of statuses or "all".', ['attribute' => $attribute]));
                }
            }],
            'status.*' => ['string', Rule::in(WorkOrder::STATUSES)],
            'line' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.($this->filled('include') ? 100 : 500)],
            'cursor' => ['nullable', 'string'],
            ...$this->includeRules(),
        ];
    }

    /**
     * Statuses to filter by: null means every status (`all`); an absent or
     * empty `status` keeps the historical default of completed orders only.
     *
     * @return list<string>|null
     */
    public function statuses(): ?array
    {
        $status = $this->input('status');

        if ($status === self::STATUS_ALL) {
            return null;
        }

        if (! is_array($status) || $status === []) {
            return [WorkOrder::STATUS_DONE];
        }

        return array_values(array_unique($status));
    }

    public function perPage(): int
    {
        return (int) $this->input('limit', $this->filled('include') ? 50 : 100);
    }
}
