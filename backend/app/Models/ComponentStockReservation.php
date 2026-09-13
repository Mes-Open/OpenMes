<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentStockReservation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'needed_at' => 'datetime'];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class, 'warehouse_stock_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(WorkOrderComponent::class, 'work_order_component_id');
    }
}
