<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdditionalCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'cost_source_id',
        'created_by_id',
        'description',
        'amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the work order this cost belongs to.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the cost source for this additional cost.
     */
    public function costSource(): BelongsTo
    {
        return $this->belongsTo(CostSource::class);
    }

    /**
     * Get the user who created this additional cost.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
