<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialAllocation extends Model
{
    const STATUS_ALLOCATED = 'allocated';

    const STATUS_CONSUMED = 'consumed';

    const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'batch_id',
        'material_id',
        'work_order_id',
        'allocated_qty',
        'returned_qty',
        'status',
        'allocated_by',
        'allocated_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'allocated_qty' => 'decimal:4',
            'returned_qty' => 'decimal:4',
            'allocated_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
