<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A stock location (#212): materials are issued out of one and finished product
 * is received into one. `kind` restricts what a warehouse may hold so a bakery's
 * flour store cannot accidentally receive finished loaves.
 */
class Warehouse extends Model
{
    use HasFactory;
    use HasTenant;
    use SoftDeletesWithAudit;

    public const KIND_RAW_MATERIAL = 'raw_material';

    public const KIND_FINISHED_GOODS = 'finished_goods';

    /** Holds both materials and finished product — the default. */
    public const KIND_MIXED = 'mixed';

    public const KINDS = [
        self::KIND_RAW_MATERIAL,
        self::KIND_FINISHED_GOODS,
        self::KIND_MIXED,
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'kind',
        'erp_code',
        'is_default',
        'is_active',
        'tenant_id',
    ];

    protected $attributes = [
        'kind' => self::KIND_MIXED,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * No softDeleteCascades(): the children a warehouse cascades to in the DB are
     * balance rows, which are not soft-deletable (derived data), and deletion is
     * refused outright while the warehouse still holds stock or carries documents
     * (WarehouseController::destroy) — so there is nothing to cascade.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function stockDocuments(): HasMany
    {
        return $this->hasMany(StockDocument::class);
    }

    public function materialLots(): HasMany
    {
        return $this->hasMany(MaterialLot::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Warehouses that may hold materials (raw-material or mixed). */
    public function scopeForMaterials(Builder $query): Builder
    {
        return $query->whereIn('kind', [self::KIND_RAW_MATERIAL, self::KIND_MIXED]);
    }

    /** Warehouses that may hold finished product (finished-goods or mixed). */
    public function scopeForProducts(Builder $query): Builder
    {
        return $query->whereIn('kind', [self::KIND_FINISHED_GOODS, self::KIND_MIXED]);
    }

    public function acceptsMaterials(): bool
    {
        return in_array($this->kind, [self::KIND_RAW_MATERIAL, self::KIND_MIXED], true);
    }

    public function acceptsProducts(): bool
    {
        return in_array($this->kind, [self::KIND_FINISHED_GOODS, self::KIND_MIXED], true);
    }

    /**
     * Resolve the warehouse an import or auto-generated document should use when
     * none was named: the default for that kind, else the default mixed one,
     * else any active warehouse able to hold the item.
     */
    public static function resolveDefault(string $kind): ?self
    {
        $usable = $kind === self::KIND_FINISHED_GOODS
            ? [self::KIND_FINISHED_GOODS, self::KIND_MIXED]
            : [self::KIND_RAW_MATERIAL, self::KIND_MIXED];

        return static::query()
            ->active()
            ->whereIn('kind', $usable)
            ->orderByDesc('is_default')
            // Prefer the kind-specific warehouse over the mixed catch-all.
            ->orderByRaw('case when kind = ? then 0 else 1 end', [$kind])
            ->orderBy('id')
            ->first();
    }

    /**
     * Marking one warehouse default clears the flag on its peers of that kind.
     *
     * One transaction: a partial unique index allows only one default per kind, so
     * a failure between the two statements would otherwise leave the kind with no
     * default at all (or block the second write outright).
     */
    public function makeDefault(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('kind', $this->kind)
                ->whereKeyNot($this->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $this->update(['is_default' => true]);
        });
    }
}
