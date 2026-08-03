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
