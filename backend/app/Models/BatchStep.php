<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchStep extends Model
{
    const STATUS_PENDING = 'PENDING';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_DONE = 'DONE';
    const STATUS_SKIPPED = 'SKIPPED';

    protected $fillable = [
        'batch_id',
        'step_number',
        'name',
        'instruction',
        'status',
        'started_at',
        'completed_at',
        'started_by_id',
        'completed_by_id',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * Get the batch that owns this step.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Get the user who started this step.
     */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_id');
    }

    /**
     * Get the user who completed this step.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /**
     * Get the issues reported for this step.
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Check if this step can be started.
     */
    public function canStart(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        // Check if work order is blocked
        $workOrder = $this->batch->workOrder;
        if ($workOrder->isBlocked()) {
            return false;
        }

        // Check if sequential steps enforcement is enabled
        $forceSequential = config('openmmes.force_sequential_steps', true);

        if (!$forceSequential) {
            return true;
        }

        // Check if previous step is complete
        if ($this->step_number === 1) {
            return true;
        }

        $previousStep = $this->batch->steps()
            ->where('step_number', $this->step_number - 1)
            ->first();

        return $previousStep && in_array($previousStep->status, [self::STATUS_DONE, self::STATUS_SKIPPED]);
    }

    /**
     * Check if this step can be completed.
     */
    public function canComplete(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
