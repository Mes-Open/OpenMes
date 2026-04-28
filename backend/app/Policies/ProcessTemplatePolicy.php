<?php

namespace App\Policies;

use App\Models\ProcessTemplate;
use App\Models\User;

class ProcessTemplatePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, ProcessTemplate $pt): bool { return true; }
    public function create(User $user): bool { return $user->hasRole('Admin'); }
    public function update(User $user, ProcessTemplate $pt): bool { return $user->hasRole('Admin'); }
    public function delete(User $user, ProcessTemplate $pt): bool { return $user->hasRole('Admin'); }
}
