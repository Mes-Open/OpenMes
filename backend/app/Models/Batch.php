<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'PENDING';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_DONE = 'DONE';
    const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'work_order_id',
        'batch_number',
        'target_qty',
        'produced_qty',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'batch_number' => 'integer',
            'target_qty' => 'decimal:2',
            'produced_qty' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the work order that owns this batch.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the steps for this batch.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(BatchStep::class)->orderBy('step_number');
    }

    /**
     * Check if all steps are complete.
     */
    public function allStepsComplete(): bool
    {
        return $this->steps()
            ->whereNotIn('status', [BatchStep::STATUS_DONE, BatchStep::STATUS_SKIPPED])
            ->count() === 0;
    }

    /**
     * Get the current (in progress or next pending) step.
     */
    public function currentStep()
    {
        // First check for in-progress step
        $inProgress = $this->steps()
            ->where('status', BatchStep::STATUS_IN_PROGRESS)
            ->first();

        if ($inProgress) {
            return $inProgress;
        }

        // Otherwise return first pending step
        return $this->steps()
            ->where('status', BatchStep::STATUS_PENDING)
            ->orderBy('step_number')
            ->first();
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
