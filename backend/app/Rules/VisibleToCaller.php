<?php

namespace App\Rules;

use App\Models\Line;
use App\Models\Workstation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * `exists:` for ids the caller is allowed to see.
 *
 * A plain `exists:lines,id` asks the table directly, so a 422-vs-200 answer
 * tells a caller whether an id exists in *another* tenant. This rule asks
 * through the model instead, so the same global scopes that hide the row from
 * every other query decide here too: TenantScope (the user's tenant, the
 * API-key TenantContext, or no restriction for a user without a tenant) and
 * soft deletes. To the caller, another tenant's id looks exactly like one that
 * was never created.
 *
 * Pair it with `bail` and `integer`, so a non-numeric value never reaches the
 * query (Postgres would reject it with a server error).
 */
class VisibleToCaller implements ValidationRule
{
    /** @param  Closure(): Builder  $query  builder for the model whose ids are checked */
    public function __construct(private Closure $query) {}

    /** A line in the caller's tenant. */
    public static function line(): self
    {
        return new self(fn () => Line::query());
    }

    /**
     * A workstation on a line in the caller's tenant. Workstations carry no
     * tenant_id of their own; they belong to a tenant through their line.
     */
    public static function workstation(): self
    {
        return new self(fn () => Workstation::query()->whereHas('line'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! ($this->query)()->whereKey($value)->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
