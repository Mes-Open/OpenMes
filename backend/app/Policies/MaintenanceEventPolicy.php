<?php

namespace App\Policies;

use App\Models\MaintenanceEvent;
use App\Models\User;

class MaintenanceEventPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, MaintenanceEvent $e): bool { return true; }
    public function create(User $u): bool { return $u->hasAnyRole(['Admin', 'Supervisor']); }
    public function update(User $u, MaintenanceEvent $e): bool { return $u->hasAnyRole(['Admin', 'Supervisor']); }
    public function delete(User $u, MaintenanceEvent $e): bool { return $u->hasRole('Admin'); }

    /** Operators assigned to a related line can also start/complete events. */
    public function transition(User $u, MaintenanceEvent $e): bool
    {
        if ($u->hasAnyRole(['Admin', 'Supervisor'])) return true;
        if ($u->id === $e->assigned_to_id) return true;
        if ($e->line_id) {
            return $u->lines()->where('lines.id', $e->line_id)->exists();
        }
        return false;
    }
}
