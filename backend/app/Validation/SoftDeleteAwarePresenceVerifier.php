<?php

namespace App\Validation;

use App\Support\SoftDeleteRegistry;
use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Presence verifier that ignores soft-deleted rows on soft-deletable tables.
 *
 * One central hook instead of appending ->whereNull('deleted_at') to ~160
 * unique/exists rules across the codebase:
 *  - `unique:` — a trashed row no longer blocks re-using its code/name/number;
 *  - `exists:` — a trashed parent no longer validates as a referenceable row.
 *
 * Registered in AppServiceProvider::boot(). Rules with explicit where
 * conditions are unaffected beyond the extra null check.
 */
class SoftDeleteAwarePresenceVerifier extends DatabasePresenceVerifier
{
    protected function table($table)
    {
        $query = parent::table($table);

        // $table may arrive as "connection.table" — registry keys are bare names.
        $name = str_contains($table, '.') ? last(explode('.', $table)) : $table;

        if (SoftDeleteRegistry::isSoftDeletable($name)) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }
}
