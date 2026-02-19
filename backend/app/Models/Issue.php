<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Issue extends Model
{
    use HasFactory, Auditable;

    const STATUS_OPEN = 'OPEN';
    const STATUS_ACKNOWLEDGED = 'ACKNOWLEDGED';
    const STATUS_RESOLVED = 'RESOLVED';
    const STATUS_CLOSED = 'CLOSED';

    protected $fillable = [
        'work_order_id',
        'batch_step_id',
        'issue_type_id',
        'title',
        'description',
        'status',
        'reported_by_id',
        'assigned_to_id',
        'reported_at',
        'acknowledged_at',
        'resolved_at',
        'closed_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get the work order for this issue.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the batch step for this issue.
     */
    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    /**
     * Get the issue type.
     */
    public function issueType(): BelongsTo
    {
        return $this->belongsTo(IssueType::class);
    }

    /**
     * Get the user who reported this issue.
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    /**
     * Get the user assigned to this issue.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * Check if this is a blocking issue.
     */
    public function isBlocking(): bool
    {
        return $this->issueType->is_blocking &&
            in_array($this->status, [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED]);
    }

    /**
     * Scope to get open issues.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED]);
    }

    /**
     * Scope to get blocking issues.
     */
    public function scopeBlocking($query)
    {
        return $query->whereHas('issueType', function ($q) {
            $q->where('is_blocking', true);
        })->open();
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
