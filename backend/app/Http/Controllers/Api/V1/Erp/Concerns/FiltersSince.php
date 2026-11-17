<?php

namespace App\Http\Controllers\Api\V1\Erp\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Incremental-sync filter shared by the ERP export endpoints: rows changed
 * at/after `?since=`.
 *
 * The value is parsed into a timestamp in the application timezone before it
 * reaches the query. Passed through as the raw string, an ISO 8601 value such
 * as `2026-11-06T18:00:00Z` is compared as text on SQLite, where the stored
 * `2026-11-06 18:17:42` sorts before it (" " < "T"), so every row was dropped.
 * Form requests already validate `since` as a date.
 */
trait FiltersSince
{
    protected function applySince(Builder $query, Request $request, string $column = 'updated_at'): void
    {
        $since = $request->input('since');

        if (! is_string($since) || $since === '') {
            return;
        }

        $query->where($column, '>=', Carbon::parse($since)->setTimezone(config('app.timezone')));
    }
}
