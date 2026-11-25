<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical location inside one warehouse: a zone (原料 A 区) or a bin under
 * that zone (A-01). Locations make material lots readable on the floor; the
 * stock balance itself stays per warehouse, so a bin never becomes a second
 * source of quantity truth.
 */
class WarehouseLocation extends Model
{
    use HasFactory;
    use HasTenant;
    use SoftDeletesWithAudit;

    public const KIND_ZONE = 'zone';

    public const KIND_BIN = 'bin';

    public const KINDS = [
        self::KIND_ZONE,
        self::KIND_BIN,
    ];

    protected $fillable = [
        'warehouse_id',
        'parent_id',
        'code',
        'name',
        'kind',
        'description',
        'sort_order',
        'is_active',
        'tenant_id',
    ];

    protected $attributes = [
        'kind' => self::KIND_BIN,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('code');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(MaterialLot::class, 'warehouse_location_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
