<?php

namespace App\Policies;

use App\Models\Tool;
use App\Models\User;

class ToolPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, Tool $t): bool { return true; }
    public function create(User $u): bool { return $u->hasRole('Admin'); }
    public function update(User $u, Tool $t): bool { return $u->hasAnyRole(['Admin', 'Supervisor']); }
    public function delete(User $u, Tool $t): bool { return $u->hasRole('Admin'); }
}
