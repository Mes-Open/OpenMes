<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, Attachment $a): bool { return true; }
    public function create(User $u): bool { return $u->hasAnyRole(['Admin', 'Supervisor', 'Operator']); }
    public function delete(User $u, Attachment $a): bool
    {
        if ($u->hasAnyRole(['Admin', 'Supervisor'])) return true;
        return $a->uploaded_by_id === $u->id;
    }
}
