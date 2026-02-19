<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use HasFactory, Auditable;
    const STATUS_PENDING = 'PENDING';
    const STATUS_ACCEPTED = 'ACCEPTED';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_BLOCKED = 'BLOCKED';
    const STATUS_PAUSED = 'PAUSED';
    const STATUS_DONE = 'DONE';
    const STATUS_REJECTED = 'REJECTED';
    const STATUS_CANCELLED = 'CANCELLED';

    /** Statuses that allow operators to work on the order */
    const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_IN_PROGRESS, self::STATUS_BLOCKED];

    /** Terminal statuses - no further transitions */
    const TERMINAL_STATUSES = [self::STATUS_DONE, self::STATUS_REJECTED, self::STATUS_CANCELLED];

    protected $fillable = [
        'order_no',
        'line_id',
        'product_type_id',
        'process_snapshot',
        'planned_qty',
        'produced_qty',
        'status',
        'priority',
        'due_date',
        'week_number',
        'month_number',
        'production_year',
        'description',
        'extra_data',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'process_snapshot' => 'array',
            'extra_data' => 'array',
            'planned_qty' => 'decimal:2',
            'produced_qty' => 'decimal:2',
            'priority' => 'integer',
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the line that owns this work order.
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * Get the product type for this work order.
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Get the batches for this work order.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class)->orderBy('batch_number');
    }

    /**
     * Get the issues for this work order.
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Get the open blocking issues for this work order.
     */
    public function openBlockingIssues()
    {
        return $this->issues()
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->whereHas('issueType', function ($query) {
                $query->where('is_blocking', true);
            })
            ->get();
    }

    /**
     * Check if this work order is blocked by any open issues.
     */
    public function isBlocked(): bool
    {
        return $this->openBlockingIssues()->isNotEmpty();
    }

    /**
     * Check if this work order is complete.
     */
    public function isComplete(): bool
    {
        $allowOverproduction = config('openmmes.allow_overproduction', false);

        if ($allowOverproduction) {
            return $this->produced_qty >= $this->planned_qty;
        }

        return (float) $this->produced_qty >= (float) $this->planned_qty;
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by line.
     */
    public function scopeForLine($query, int $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    /**
     * Scope to get work orders for a specific user's assigned lines.
     */
    public function scopeForUser($query, User $user)
    {
        // Admins and Supervisors see all work orders regardless of line assignment
        if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
            return $query;
        }

        $lineIds = $user->lines()->pluck('lines.id');
        return $query->whereIn('line_id', $lineIds);
    }

    /**
     * Scope to order by priority and due date.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc')
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'asc');
    }
}
