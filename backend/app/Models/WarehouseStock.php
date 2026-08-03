<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stock balance in one warehouse for one item (#212).
 *
 * Not soft-deletable: a balance is derived data, and a slot that empties keeps
 * a zero row so its history and unit of measure survive.
 */
class WarehouseStock extends Model
{
    use HasFactory;
    use HasTenant;

    protected $fillable = [
        'warehouse_id',
        'material_id',
        'product_type_id',
        'material_lot_id',
        'quantity',
        'unit_of_measure',
        'erp_synced_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'erp_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $stock) => $stock->assertItem());
    }

    /**
     * A balance row must name exactly one item — a material or a product type —
     * and a lot only qualifies a material.
     *
     * The partial unique indexes keep one row per slot but cannot express "one of
     * these two columns", so the invariant is enforced here: a row with both (or
     * neither) set would be counted by one view and missed by another, and a lot
     * without its material has nothing to belong to.
     */
    public function assertItem(): void
    {
        if (($this->material_id === null) === ($this->product_type_id === null)) {
            throw new \InvalidArgumentException(
                'A warehouse stock row must reference exactly one of material_id or product_type_id.'
            );
        }

        if ($this->material_lot_id !== null && $this->material_id === null) {
            throw new \InvalidArgumentException(
                'A warehouse stock row with a material lot must also reference its material.'
            );
        }
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }
}
