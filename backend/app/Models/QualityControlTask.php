<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fired instance of a QualityControlTrigger (#105): a control that is due to
 * be performed, linked to the work order / batch / machine it concerns. When
 * performed it links the recorded QualityCheck (and, on failure, the Issue).
 */
class QualityControlTask extends Model
{
    use Auditable, HasFactory;
    use SoftDeletesWithAudit;

    const STATUS_DUE = 'due';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_DONE = 'done';

    const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [self::STATUS_DUE, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_SKIPPED];

    /** Statuses where the control still needs doing (drives the blocking gate). */
    public const OPEN_STATUSES = [self::STATUS_DUE, self::STATUS_IN_PROGRESS];

    protected $fillable = [
        'quality_control_trigger_id',
        'status',
        'work_order_id',
        'batch_id',
        'workstation_id',
        'line_id',
        'due_reason',
        'quality_check_id',
        'issue_id',
        'fired_at',
        'completed_at',
        'completed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'fired_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(QualityControlTrigger::class, 'quality_control_trigger_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function qualityCheck(): BelongsTo
    {
        return $this->belongsTo(QualityCheck::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    /**
     * Is there an open, blocking control outstanding for this batch? Drives the
     * hard gate on next-step start and batch release.
     */
    public static function hasOpenBlockingForBatch(int $batchId): bool
    {
        return self::query()
            ->open()
            ->where('batch_id', $batchId)
            ->whereHas('trigger', fn ($q) => $q->where('is_blocking', true))
            ->exists();
    }
}
