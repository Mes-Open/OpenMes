<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Services\Material\BomExplosionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Material extends Model
{
    use HasCustomFields, HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'code',
        'name',
        'description',
        'material_type_id',
        'unit_of_measure',
        'tracking_type',
        'is_manufactured',
        'producing_process_template_id',
        'default_scrap_percentage',
        'stock_quantity',
        'reserved_quantity',
        'min_stock_level',
        'extra_data',
        'external_code',
        'external_system',
        'supplier_name',
        'supplier_code',
        'unit_price',
        'price_currency',
        'ean',
        'last_stock_sync_at',
        'is_active',
        'tenant_id',
    ];

    /**
     * The column default lives in the DB, but a freshly built model wouldn't
     * reflect it until reloaded — leaving `is_manufactured` null in memory and
     * make-or-buy checks reading a null instead of false.
     */
    protected $attributes = [
        'is_manufactured' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_manufactured' => 'boolean',
            'default_scrap_percentage' => 'decimal:2',
            'stock_quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'min_stock_level' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'last_stock_sync_at' => 'datetime',
            'extra_data' => 'array',
        ];
    }

    /** Cached physical quantity sitting in quality-held lots for this instance. */
    protected ?float $qualityHeldCache = null;

    /**
     * Material physically present minus what is currently reserved by
     * active allocations. The truthful "free to allocate" number.
     *
     * Lots held by quality (quarantine/rejected) are still physically in the
     * warehouse but may not be issued. Subtracting their remaining quantity
     * keeps the aggregate stock view aligned with what lot picking can use.
     */
    public function getAvailableQuantityAttribute(): float
    {
        return (float) $this->stock_quantity - (float) $this->reserved_quantity - $this->quality_held_quantity;
    }

    /**
     * Remaining physical quantity in lots that quality has not released.
     *
     * A lot created while quarantined starts with quantity_available = 0, so
     * quantity_available cannot be used here; remaining is received minus
     * consumed. Only lot/serial tracked materials have meaningful held lots.
     */
    public function getQualityHeldQuantityAttribute(): float
    {
        if (! in_array($this->tracking_type, ['batch', 'serial'], true)) {
            return 0.0;
        }

        if ($this->qualityHeldCache !== null) {
            return $this->qualityHeldCache;
        }

        return $this->qualityHeldCache = (float) $this->lots()
            ->whereIn('status', [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED])
            ->withSum('consumptions as consumed_quantity', 'quantity_consumed')
            ->withSum('disposals as disposed_quantity', 'quantity')
            ->get(['id', 'quantity_received'])
            ->sum(fn ($lot) => max(0,
                (float) $lot->quantity_received
                - (float) ($lot->consumed_quantity ?? 0)
                - (float) ($lot->disposed_quantity ?? 0)
            ));
    }

    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(MaterialSource::class);
    }

    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    /**
     * Physical lots received against this material (ISA-95).
     */
    public function lots(): HasMany
    {
        return $this->hasMany(MaterialLot::class);
    }

    /** Per-warehouse balances for this material (#212). */
    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    protected static function booted(): void
    {
        // One currency per installation: a price nobody labelled (the material
        // form has no currency field) is in the installation currency, not in
        // the column's historical PLN default.
        static::creating(function (self $material): void {
            if (blank($material->price_currency)) {
                $material->price_currency = \App\Support\CurrencyRegistry::current();
            }
        });

        // The other way to close a BOM loop: point an item at a producing
        // template that already consumes it, at any depth. Enforced on the
        // model so every write path is covered (the BOM-line side is gated in
        // BomService). ValidationException so HTTP surfaces answer 422.
        static::saving(function (self $material): void {
            // The column is NOT NULL with a DB default, so a form field that
            // arrives blank (ConvertEmptyStringsToNull) must not be written
            // through as an explicit null.
            if ($material->is_manufactured === null) {
                $material->is_manufactured = false;
            }

            // Only an existing row can already be a component somewhere, so a
            // freshly created material can't close a loop.
            if (! $material->isDirty('producing_process_template_id')
                || $material->producing_process_template_id === null
                || ! $material->exists) {
                return;
            }

            $creates = app(BomExplosionService::class)
                ->wouldCreateCycleAsProducer($material, (int) $material->producing_process_template_id);

            if ($creates) {
                throw ValidationException::withMessages([
                    'producing_process_template_id' => __(
                        'That template already consumes :material, so it cannot also produce it.',
                        ['material' => $material->code],
                    ),
                ]);
            }
        });
    }

    /**
     * The BOM/routing that produces this material, for items made in-house.
     * Null on purchased components — and on manufactured ones whose template
     * hasn't been created yet, which BOM explosion treats as a leaf.
     */
    public function producingTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class, 'producing_process_template_id');
    }

    /**
     * Made in-house *and* explodable — what lets a BOM line descend a level.
     *
     * The relation itself has to resolve, not just the FK: process templates
     * soft-delete, and `nullOnDelete` only fires on a hard delete, so a
     * soft-deleted template leaves the id pointing at a row the relation
     * returns null for. Without this check explosion would descend into a null
     * template. Such a material falls back to being a leaf — a requirement for
     * the subassembly itself — which is the safe reading.
     */
    public function isExplodable(): bool
    {
        return $this->is_manufactured
            && $this->producing_process_template_id !== null
            && $this->producingTemplate !== null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Subassemblies produced in-house. */
    public function scopeManufactured(Builder $query): Builder
    {
        return $query->where('is_manufactured', true);
    }

    /** Purchased components and raw materials. */
    public function scopePurchased(Builder $query): Builder
    {
        return $query->where('is_manufactured', false);
    }

    public function scopeByExternalCode($query, string $code, ?string $system = null)
    {
        $query->where('external_code', $code);

        if ($system) {
            $query->where('external_system', $system);
        }

        return $query;
    }
}
