<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationLotPick extends Model
{
    use HasFactory;
    use HasTenant;

    public const STRATEGY_FEFO = 'fefo';

    public const STRATEGY_FIFO = 'fifo';

    public const STRATEGY_LIFO = 'lifo';

    public const STRATEGY_MANUAL = 'manual';

    protected $fillable = [
        'material_allocation_id',
        'material_lot_id',
        // The location this pick was deducted from, frozen at the first deduction.
        'consumption_warehouse_id',
        'tenant_id',
        'picked_qty',
        'picking_strategy',
    ];

    protected function casts(): array
    {
        return [
            'picked_qty' => 'decimal:4',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(MaterialAllocation::class, 'material_allocation_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    /** The location this pick was consumed from, once anything has been deducted. */
    public function consumptionWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'consumption_warehouse_id');
    }
}
