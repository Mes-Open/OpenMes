<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'workstation_id',
        'work_order_id',
        'batch_id',
        'production_cycle_id',
        'event_type',
        'quantity',
        'anomaly_reason_id',
        'occurred_at',
        'worker_id',
        'notes',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'quantity' => 'float',
    ];

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    public function anomalyReason(): BelongsTo
    {
        return $this->belongsTo(AnomalyReason::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
