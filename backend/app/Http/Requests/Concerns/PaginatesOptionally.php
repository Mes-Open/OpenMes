<?php

namespace App\Http\Requests\Concerns;

/**
 * Opt-in pagination for a list endpoint that historically returned a capped,
 * unpaginated `{data: [...]}` array.
 *
 * Existing clients send neither `page` nor `per_page` and must keep getting
 * exactly the legacy response, so paging only switches on when the caller asks
 * for it by sending at least one of the two parameters.
 */
trait PaginatesOptionally
{
    /** Upper bound for `per_page`. */
    public const MAX_PER_PAGE = 100;

    /** Validation rules for `page` / `per_page`. */
    protected function paginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /** True when the caller opted into a paginated response. */
    public function wantsPagination(): bool
    {
        return $this->filled('page') || $this->filled('per_page');
    }

    /** Requested page size, falling back to the endpoint's legacy cap. */
    public function perPage(int $default): int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : $default;
    }

    /** Requested page number (1-based). */
    public function pageNumber(): int
    {
        return $this->filled('page') ? $this->integer('page') : 1;
    }
}
