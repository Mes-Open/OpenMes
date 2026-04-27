<?php

namespace App\Policies;

use App\Models\AdditionalCost;
use App\Models\User;

class AdditionalCostPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, AdditionalCost $c): bool { return true; }
    public function create(User $u): bool { return $u->hasAnyRole(['Admin', 'Supervisor']); }
    public function update(User $u, AdditionalCost $c): bool { return $u->hasAnyRole(['Admin', 'Supervisor']); }
    public function delete(User $u, AdditionalCost $c): bool { return $u->hasAnyRole(['Admin', 'Supervisor']); }
}
