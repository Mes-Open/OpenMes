<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Support\Units\UnitNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One name an ERP uses for a unit ("Stück", "KGM") and the unit OpenMES stores
 * for it ("pcs", "kg"). Read by App\Support\Units\UnitNormalizer during ERP
 * imports; edited on Settings → Units.
 */
class UnitAlias extends Model
{
    use HasFactory;
    use HasTenant;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'alias',
        'unit',
        'tenant_id',
    ];

    protected static function booted(): void
    {
        // The lookup key is derived, never typed — so it cannot drift from
        // the alias it belongs to.
        static::saving(function (UnitAlias $alias): void {
            $alias->alias = trim((string) $alias->alias);
            $alias->unit = trim((string) $alias->unit);
            $alias->alias_normalized = UnitNormalizer::key($alias->alias);
        });
    }
}
