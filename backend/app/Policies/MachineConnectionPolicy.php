<?php

namespace App\Policies;

use App\Models\MachineConnection;
use App\Models\User;

class MachineConnectionPolicy
{
    public function viewAny(User $u): bool { return $u->hasRole('Admin'); }
    public function view(User $u, MachineConnection $c): bool { return $u->hasRole('Admin'); }
    public function create(User $u): bool { return $u->hasRole('Admin'); }
    public function update(User $u, MachineConnection $c): bool { return $u->hasRole('Admin'); }
    public function delete(User $u, MachineConnection $c): bool { return $u->hasRole('Admin'); }
}
