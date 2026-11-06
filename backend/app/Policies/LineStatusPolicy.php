<?php

namespace App\Policies;

use App\Models\LineStatus;
use App\Models\User;

class LineStatusPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, LineStatus $s): bool { return true; }
    public function create(User $user): bool { return $user->hasRole('Admin'); }
    public function update(User $user, LineStatus $s): bool { return $user->hasRole('Admin'); }
    public function delete(User $user, LineStatus $s): bool { return $user->hasRole('Admin'); }
}
