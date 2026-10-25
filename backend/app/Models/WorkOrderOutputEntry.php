<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a work order's `produced_qty`, recorded when it happened.
 *
 * Written only by {@see \App\Observers\WorkOrderOutputObserver}; the sum of an
 * order's deltas equals its produced_qty change since the ledger existed.
 */
class WorkOrderOutputEntry extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'line_id',
        'delta',
        'produced_total',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'decimal:2',
            'produced_total' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }
}
