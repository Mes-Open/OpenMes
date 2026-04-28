<?php

namespace App\Policies;

use App\Models\ProductionAnomaly;
use App\Models\User;

class ProductionAnomalyPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, ProductionAnomaly $a): bool { return true; }
    public function create(User $u): bool { return $u->hasAnyRole(['Admin', 'Supervisor', 'Operator']); }
    public function update(User $u, ProductionAnomaly $a): bool
    {
        if ($u->hasAnyRole(['Admin', 'Supervisor'])) return true;
        // Operator can edit their own draft
        return $a->created_by_id === $u->id && $a->status === ProductionAnomaly::STATUS_DRAFT;
    }
    public function delete(User $u, ProductionAnomaly $a): bool { return $u->hasRole('Admin'); }
    public function process(User $u, ProductionAnomaly $a): bool { return $u->hasAnyRole(['Admin', 'Supervisor']); }
}
