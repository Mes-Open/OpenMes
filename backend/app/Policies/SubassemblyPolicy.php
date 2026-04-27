<?php

namespace App\Policies;

use App\Models\Subassembly;
use App\Models\User;

class SubassemblyPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, Subassembly $s): bool { return true; }
    public function create(User $u): bool { return $u->hasRole('Admin'); }
    public function update(User $u, Subassembly $s): bool { return $u->hasRole('Admin'); }
    public function delete(User $u, Subassembly $s): bool { return $u->hasRole('Admin'); }
}
