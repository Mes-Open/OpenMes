<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail for a bin-to-bin location change. Stock quantities are never
 * touched here: a move only repoints the lot or product balance at another
 * physical location, so quantity remains owned by the stock ledger.
 */
class WarehouseLocationMove extends Model
{
    use HasFactory;
    use HasTenant;

    public const ITEM_MATERIAL_LOT = 'material_lot';

    public const ITEM_PRODUCT_STOCK = 'product_stock';

    public const ITEM_TYPES = [
        self::ITEM_MATERIAL_LOT,
        self::ITEM_PRODUCT_STOCK,
    ];

    protected $fillable = [
        'warehouse_id',
        'from_location_id',
        'to_location_id',
        'item_type',
        'item_id',
        'item_code',
        'item_name',
        'quantity',
        'unit_of_measure',
        'moved_by',
        'moved_at',
        'note',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'moved_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'to_location_id');
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
