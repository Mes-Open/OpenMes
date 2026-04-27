<?php

namespace App\Policies;

use App\Models\AnomalyReason;
use App\Models\User;

class AnomalyReasonPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, AnomalyReason $a): bool { return true; }
    public function create(User $u): bool { return $u->hasRole('Admin'); }
    public function update(User $u, AnomalyReason $a): bool { return $u->hasRole('Admin'); }
    public function delete(User $u, AnomalyReason $a): bool { return $u->hasRole('Admin'); }
}
