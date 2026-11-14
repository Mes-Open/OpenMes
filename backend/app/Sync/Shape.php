<?php

namespace App\Sync;

use App\Models\User;
use App\Support\ModuleRegistry;
use App\Support\TabRegistry;

/**
 * A named, server-defined synced collection.
 *
 * Clients never pick the table, columns, or WHERE clause — they pick a
 * *collection name* and the server resolves it to this definition with
 * auth/tenancy already baked in. The scope set here is the whole scope: the
 * snapshot endpoint (CollectionController) and the delta broadcast
 * (CollectionChanged) both build their query from it, and neither accepts a
 * client-supplied filter.
 *
 * Who may read it is part of the definition too: allows() gates both the
 * snapshot and the live channel (routes/channels.php), so the two can't drift.
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

    /**
     * Admin-panel tabs (TabRegistry keys) whose pages read this collection. A
     * user granted any of them in the access matrix may read it — the same
     * permission that lets them open the page.
     *
     * @return list<string>
     */
    public function tabs(): array
    {
        return [];
    }

    /**
     * Roles that may read this collection outright, for screens gated by role
     * rather than by an admin tab (e.g. a shop-floor terminal).
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return [];
    }

    /**
     * Whether the user may read this collection. Admin and Supervisor read
     * every collection; anyone else needs one of the collection's roles or
     * tabs. A collection that declares neither stays Admin/Supervisor only.
     */
    final public function allows(User $user): bool
    {
        if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
            return true;
        }

        if ($this->roles() !== [] && $user->hasAnyRole($this->roles())) {
            return true;
        }

        foreach ($this->tabs() as $tab) {
            if (ModuleRegistry::isTabEnabled($tab) && $user->can(TabRegistry::permission($tab))) {
                return true;
            }
        }

        return false;
    }
}
