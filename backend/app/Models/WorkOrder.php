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
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_BLOCKED = 'BLOCKED';
    const STATUS_DONE = 'DONE';
    const STATUS_CANCELLED = 'CANCELLED';

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
        'description',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'process_snapshot' => 'array', // JSONB field
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

        return bccomp($this->produced_qty, $this->planned_qty, 2) >= 0;
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
