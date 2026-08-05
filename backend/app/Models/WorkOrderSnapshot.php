<?php

namespace App\Models;

use App\Enums\ChangeEffectivePoint;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a work order's configuration (#182).
 *
 * Version 1 is what the order was created with; every applied change adds the next
 * version. Append-only: a snapshot is the record of what the shop floor was told to
 * build, so it is never edited and never deleted.
 */
class WorkOrderSnapshot extends Model
{
    use HasFactory;
    use HasTenant;

    protected $fillable = [
        'work_order_id',
        'version',
        'snapshot',
        'effective_from',
        'effective_from_qty',
        'effective_from_batch_id',
        'change_request_id',
        'created_by_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'version' => 'integer',
            'effective_from' => ChangeEffectivePoint::class,
            'effective_from_qty' => 'decimal:2',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(WorkOrderChangeRequest::class, 'change_request_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** The batch this version first applies to, for NEXT_BATCH changes. */
    public function effectiveFromBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'effective_from_batch_id');
    }

    public function scopeForOrder(Builder $query, int $workOrderId): Builder
    {
        return $query->where('work_order_id', $workOrderId)->orderBy('version');
    }

    /** The revision block frozen into this version (#180), when the order has one. */
    public function revision(): ?array
    {
        return $this->snapshot['revision'] ?? null;
    }

    /** The engineering documents frozen into this version (#179). */
    public function engineeringDocuments(): array
    {
        return $this->snapshot['engineering_documents'] ?? [];
    }

    /**
     * Human summary of where this version starts, for history lines.
     */
    public function effectivePointDescription(): string
    {
        return match ($this->effective_from) {
            ChangeEffectivePoint::NextBatch => $this->effective_from_batch_id
                ? __('From batch #:batch', ['batch' => $this->effective_from_batch_id])
                : __('From the next batch'),
            ChangeEffectivePoint::RemainingQuantity => __('From unit :qty onwards', [
                'qty' => (float) $this->effective_from_qty + 1,
            ]),
            ChangeEffectivePoint::Immediate => __('Immediately'),
        };
    }
}
