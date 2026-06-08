<?php

namespace App\Sync;

use App\Models\User;

/**
 * A named, server-defined Electric shape.
 *
 * The proxy controller never lets clients pick the table, columns, or main
 * WHERE clause directly — they pick a *shape name* and the server resolves
 * it to an Electric shape definition with auth/tenancy already baked in.
 *
 * Clients can append a narrower `where=` (Electric only allows narrowing
 * within the server's main WHERE), but cannot escape the scope set here.
 */
abstract class Shape
{
    /** Database table to sync from. */
    abstract public function table(): string;

    /** Whitelisted columns to expose. Never include password hashes, tokens, PII. */
    abstract public function columns(): array;

    /**
     * Server-controlled WHERE clause. Receives the authenticated user so the
     * shape can scope to their tenant, line assignments, etc.
     *
     * Return null for unfiltered (use sparingly — only for truly public data).
     */
    abstract public function where(User $user): ?string;
}
