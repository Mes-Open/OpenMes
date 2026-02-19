<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionAnomaly extends Model
{
    use HasFactory, Auditable;

    const STATUS_DRAFT     = 'draft';
    const STATUS_PROCESSED = 'processed';

    protected $fillable = [
        'work_order_id',
        'batch_id',
        'batch_step_id',
        'anomaly_reason_id',
        'created_by_id',
        'product_name',
        'planned_qty',
        'actual_qty',
        'deviation_pct',
        'status',
        'comment',
    ];

    protected static function booted(): void
    {
        // Auto-compute deviation_pct on save so it's always in sync
        static::saving(function (self $model) {
            if ($model->planned_qty > 0) {
                $model->deviation_pct = round(
                    (($model->actual_qty - $model->planned_qty) / $model->planned_qty) * 100,
                    2
                );
            } else {
                $model->deviation_pct = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'planned_qty'   => 'decimal:2',
            'actual_qty'    => 'decimal:2',
            'deviation_pct' => 'decimal:2',
        ];
    }

    /**
     * Get the work order this anomaly belongs to.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the batch this anomaly belongs to.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Get the batch step this anomaly belongs to.
     */
    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    /**
     * Get the anomaly reason for this anomaly.
     */
    public function anomalyReason(): BelongsTo
    {
        return $this->belongsTo(AnomalyReason::class);
    }

    /**
     * Get the user who created this anomaly.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Scope to get only draft anomalies.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }
}
