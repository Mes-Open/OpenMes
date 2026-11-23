<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pieces of a non-conformance sent back to an earlier operation (#400, item 2).
 *
 * While open or in progress, `quantity` pieces are held at the source step
 * (where the defect was found). Completing it releases the accepted pieces to
 * that step again and books the rest as scrap there; cancelling releases all.
 * See ReworkService.
 */
class ReworkOperation extends Model
{
    use Auditable;
    use SoftDeletesWithAudit;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses that still hold pieces at the source step. */
    public const ACTIVE_STATUSES = [self::STATUS_OPEN, self::STATUS_IN_PROGRESS];

    protected $fillable = [
        'issue_id',
        'work_order_id',
        'batch_id',
        'source_batch_step_id',
        'target_batch_step_id',
        'quantity',
        'reason',
        'status',
        'accepted_qty',
        'scrapped_qty',
        'scrap_entry_id',
        'notes',
        'actual_labor_minutes',
        'actual_machine_minutes',
        'started_at',
        'started_by_id',
        'completed_at',
        'completed_by_id',
        'cancelled_at',
        'cancelled_by_id',
        'cancel_reason',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'accepted_qty' => 'decimal:2',
            'scrapped_qty' => 'decimal:2',
            'actual_labor_minutes' => 'integer',
            'actual_machine_minutes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function sourceStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class, 'source_batch_step_id');
    }

    public function targetStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class, 'target_batch_step_id');
    }

    public function scrapEntry(): BelongsTo
    {
        return $this->belongsTo(ScrapEntry::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(BatchStepTimeEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }
}
