<?php

namespace App\Policies;

use App\Models\ScrapEntry;
use App\Models\User;

class ScrapEntryPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, ScrapEntry $e): bool { return true; }
    public function create(User $u): bool { return $u->hasAnyRole(['Admin', 'Supervisor', 'Operator']); }
    public function update(User $u, ScrapEntry $e): bool
    {
        if ($u->hasAnyRole(['Admin', 'Supervisor'])) return true;
        // Operator can edit their own entry
        return $e->reported_by === $u->id;
    }
    public function delete(User $u, ScrapEntry $e): bool { return $u->hasAnyRole(['Admin', 'Supervisor']); }
}
