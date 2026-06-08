<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Sync\Shape;

/**
 * Issue type lookup. Tiny table (tens of rows) — synced so the client can
 * derive `is_blocking` and a human-readable name for each issue without
 * the proxy having to do server-side joins.
 */
class IssueTypesShape extends Shape
{
    public function table(): string
    {
        return 'issue_types';
    }

    public function columns(): array
    {
        return ['id', 'code', 'name', 'severity', 'is_blocking', 'is_active'];
    }

    public function where(User $user): ?string
    {
        return 'is_active = true';
    }
}
