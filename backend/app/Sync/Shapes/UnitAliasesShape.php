<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Sync\Shape;

/**
 * Unit aliases (Settings → Units of Measure).
 *
 * The defaults are stored once for the install (tenant NULL) and once per
 * tenant, so each tenant admin can edit their own copy. A tenant user is
 * already narrowed to their tenant by CollectionController; a user without a
 * tenant is not narrowed at all and would see every copy — each default alias
 * several times over. Show them the install-wide rows only: those are the
 * aliases their own ERP imports resolve through (UnitNormalizer).
 */
class UnitAliasesShape extends Shape
{
    public function table(): string
    {
        return 'unit_aliases';
    }

    public function columns(): array
    {
        return ['id', 'alias', 'unit', 'created_at', 'updated_at'];
    }

    public function where(User $user): ?string
    {
        return $user->tenant_id === null ? 'tenant_id IS NULL' : null;
    }
}
