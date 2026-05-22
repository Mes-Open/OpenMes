<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialAllocation extends Model
{
    use Auditable;
    use HasFactory;
    use HasTenant;

    const STATUS_ALLOCATED = 'allocated';

    const STATUS_CONSUMED = 'consumed';

    const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'batch_id',
        'batch_step_id',
        'material_id',
        'work_order_id',
        'allocated_qty',
        'expected_qty',
        'returned_qty',
        'consumed_qty',
        'adjustment_qty',
        'scrap_qty',
        'status',
        'allocated_by',
        'allocated_at',
        'consumed_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'allocated_qty' => 'decimal:4',
            'expected_qty' => 'decimal:4',
            'returned_qty' => 'decimal:4',
            'consumed_qty' => 'decimal:4',
            'adjustment_qty' => 'decimal:4',
            'scrap_qty' => 'decimal:4',
            'allocated_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Difference between what was actually consumed and what was expected.
     * Positive = used more than BOM said. Useful for scrap analysis.
     */
    public function getVarianceQtyAttribute(): float
    {
        return (float) $this->consumed_qty - (float) $this->expected_qty;
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
