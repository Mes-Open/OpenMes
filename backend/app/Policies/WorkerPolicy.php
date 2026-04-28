<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;

class WorkerPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Worker $worker): bool { return true; }
    public function create(User $user): bool { return $user->hasRole('Admin'); }
    public function update(User $user, Worker $worker): bool { return $user->hasRole('Admin'); }
    public function delete(User $user, Worker $worker): bool { return $user->hasRole('Admin'); }
    public function manageSkills(User $user, Worker $worker): bool { return $user->hasRole('Admin'); }
}
