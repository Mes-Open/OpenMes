<?php

namespace App\Policies;

use App\Models\CostSource;
use App\Models\User;

class CostSourcePolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, CostSource $c): bool { return true; }
    public function create(User $u): bool { return $u->hasRole('Admin'); }
    public function update(User $u, CostSource $c): bool { return $u->hasRole('Admin'); }
    public function delete(User $u, CostSource $c): bool { return $u->hasRole('Admin'); }
}
