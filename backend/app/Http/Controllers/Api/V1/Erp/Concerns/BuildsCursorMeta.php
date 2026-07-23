<?php

namespace App\Http\Controllers\Api\V1\Erp\Concerns;

use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Shared `meta` envelope for the cursor-paginated ERP export endpoints. The ERP
 * follows `next_cursor` until it is null to walk the full result set.
 */
trait BuildsCursorMeta
{
    /** @return array{next_cursor: string|null, has_more: bool, count: int, per_page: int} */
    protected function cursorMeta(CursorPaginator $paginator): array
    {
        return [
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
            'count' => count($paginator->items()),
            'per_page' => $paginator->perPage(),
        ];
    }
}
