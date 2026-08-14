<?php

namespace App\Models;

use App\Enums\WorkOrderStopType;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A structured production stop on a work order (#182).
 *
 * Records why production stopped, the state it was in at that moment, and who
 * resumed it. The captured state is a photograph: produced quantity, active batch
 * and configuration version are copied here and never read back into execution, so
 * a stop can never rewrite what the shop floor already did.
 *
 * Append-only history, so not soft-deletable.
 */
class WorkOrderStop extends Model
{
    use HasFactory;
    use HasTenant;

    protected $fillable = [
        'work_order_id',
        'batch_id',
        'type',
        'reason',
        'requires_change',
        'produced_qty_at_stop',
        'snapshot_version_at_stop',
        'context',
        'production_downtime_id',
        'issue_id',
        'stopped_by_id',
        'stopped_at',
        'resumed_by_id',
        'resumed_at',
        'resume_notes',
        'duration_minutes',
        'applied_change_request_id',
        'resulting_status',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkOrderStopType::class,
            'requires_change' => 'boolean',
            'produced_qty_at_stop' => 'decimal:2',
            'snapshot_version_at_stop' => 'integer',
            'context' => 'array',
            'stopped_at' => 'datetime',
            'resumed_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function downtime(): BelongsTo
    {
        return $this->belongsTo(ProductionDowntime::class, 'production_downtime_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function stoppedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stopped_by_id');
    }

    public function resumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resumed_by_id');
    }

    public function appliedChangeRequest(): BelongsTo
    {
        return $this->belongsTo(WorkOrderChangeRequest::class, 'applied_change_request_id');
    }

    /** Stops that have not been resumed — at most one per order at a time. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resumed_at');
    }

    public function isOpen(): bool
    {
        return $this->resumed_at === null;
    }

    /**
     * Minutes production was down. Uses the stored value once resumed, and counts
     * from the stop for one still open, so a status page can show a running total.
     */
    public function durationMinutes(): int
    {
        if ($this->duration_minutes !== null) {
            return $this->duration_minutes;
        }

        return (int) $this->stopped_at->diffInMinutes($this->resumed_at ?? now());
    }
}
