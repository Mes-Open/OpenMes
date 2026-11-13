<?php

namespace App\Http\Requests\Api\V1;

use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters for `GET /api/v1/work-orders`.
 *
 * Every parameter is optional, and a request without any of the new ones
 * (`page`, `per_page`, `search`, `due_from`, `due_to`, `include`) gets exactly
 * the legacy response: every visible order, unpaginated, with `line`,
 * `product_type` and `batches.steps` loaded.
 *
 * Pagination is switched on by `page` alone. `per_page` only sizes the pages
 * and is ignored without `page`: the mobile app has long sent `per_page=100`
 * and `per_page=200` to this endpoint, which never paginated, and it relies on
 * getting every order back.
 */
class ListWorkOrdersRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /**
     * `include` token => Eloquent relation. The keys are the JSON keys the
     * relations serialise under, so a client asks for what it reads back.
     */
    public const INCLUDES = [
        'line' => 'line',
        'product_type' => 'productType',
        'batches' => 'batches',
        'batches.steps' => 'batches.steps',
    ];

    public const MAX_PAGE = 1_000_000;

    public function authorize(): bool
    {
        // The endpoint's only authorization check. A FormRequest authorizes
        // before it validates, so a user without the right role gets 403 even
        // when the query string is also malformed.
        return $this->user()?->can('viewAny', WorkOrder::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // `include=line,product_type` (comma list) and `include[]=line` both work.
        if (is_string($this->input('include'))) {
            $this->merge([
                'include' => array_values(array_filter(
                    array_map('trim', explode(',', $this->input('include'))),
                    fn (string $token) => $token !== '',
                )),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // Legacy filters. Unknown status values have always returned an empty
            // list rather than an error, so the value itself is not enumerated.
            'status' => ['sometimes', 'nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if (! is_string($value) && ! is_array($value)) {
                    $fail(__('The status must be a string or a list of strings.'));
                }
            }],
            'status.*' => ['string'],
            'line_id' => ['sometimes', 'nullable', 'integer'],

            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Strict ISO dates only: the loose `date` rule accepts `10.03.2026`
            // (which Postgres would read as 3 Oct) and relative strings.
            'due_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'due_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:due_from'],

            // `full` (default): every column. `summary`: scalar columns only.
            'fields' => ['sometimes', 'nullable', Rule::in(['full', 'summary'])],

            'include' => ['sometimes', 'nullable', 'array'],
            'include.*' => ['string', Rule::in(array_keys(self::INCLUDES))],

            'page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
            // Without `page` the value is dropped unvalidated (see class doc).
            'per_page' => ['exclude_without:page', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * Filters for WorkOrderService::queryWorkOrdersForUser().
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['status', 'line_id', 'search', 'due_from', 'due_to']),
            fn ($value) => $value !== null && $value !== '',
        );
    }

    /**
     * Eloquent relations to eager-load, in request order. Without `include`,
     * the service's legacy list relations, so the default can never drift.
     *
     * @return list<string>
     */
    public function relations(): array
    {
        $tokens = $this->validated('include');

        if ($tokens === null) {
            return WorkOrderService::LIST_RELATIONS;
        }

        return array_values(array_unique(array_map(fn (string $token) => self::INCLUDES[$token], $tokens)));
    }

    /**
     * The work_orders columns to select: null (every column) unless the client
     * asked for `fields=summary`.
     *
     * @return list<string>|null
     */
    public function columns(): ?array
    {
        return $this->validated('fields') === 'summary' ? WorkOrderService::LIST_SUMMARY_COLUMNS : null;
    }

    public function wantsPagination(): bool
    {
        return $this->validated('page') !== null;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PER_PAGE);
    }
}
