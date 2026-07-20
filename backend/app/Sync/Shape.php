<?php

namespace App\Sync;

use App\Models\User;

/**
 * A named, server-defined synced collection.
 *
 * Clients never pick the table, columns, or WHERE clause — they pick a
 * *collection name* and the server resolves it to this definition with
 * auth/tenancy already baked in. The scope set here is the whole scope: the
 * snapshot endpoint (CollectionController) and the delta broadcast
 * (CollectionChanged) both build their query from it, and neither accepts a
 * client-supplied filter.
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
