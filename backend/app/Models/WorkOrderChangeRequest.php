<?php

namespace App\Models;

use App\Enums\ChangeEffectivePoint;
use App\Enums\ChangeRequestStatus;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A controlled production-change request (#182).
 *
 * Holds what somebody wants to change about a running work order, the impact that
 * was shown to the approver, and the audit trail of who asked, who approved and when
 * it was applied. Nothing here modifies the order — applying it does, and only once,
 * through ChangeRequestService.
 *
 * Not soft-deletable on purpose: a withdrawn request is CANCELLED and stays
 * readable, because "we decided not to do this, and here is why" is exactly the kind
 * of thing an audit asks about.
 */
class WorkOrderChangeRequest extends Model
{
    use HasFactory;
    use HasTenant;

    /** Fields a request is allowed to propose. Anything else is ignored by design. */
    public const CHANGEABLE_FIELDS = [
        'product_revision_id',
        'planned_qty',
        'line_id',
        'bom_template_ids',
        'due_date',
        'description',
        'production_notes',
    ];

    protected $fillable = [
        'code',
        'work_order_id',
        'work_order_stop_id',
        'title',
        'reason',
        'status',
        'proposed',
        'previous_values',
        'impact',
        'effective_from',
        'effective_from_batch_id',
        'produced_disposition',
        'material_disposition',
        'implementation_notes',
        'rejection_reason',
        'requested_by_id',
        'submitted_at',
        'approved_by_id',
        'approved_at',
        'rejected_by_id',
        'rejected_at',
        'applied_by_id',
        'applied_at',
        'resulting_snapshot_version',
        'tenant_id',
    ];

    protected $attributes = [
        'status' => ChangeRequestStatus::Draft->value,
        'effective_from' => ChangeEffectivePoint::NextBatch->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => ChangeRequestStatus::class,
            'effective_from' => ChangeEffectivePoint::class,
            'proposed' => 'array',
            'previous_values' => 'array',
            'impact' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'applied_at' => 'datetime',
            'resulting_snapshot_version' => 'integer',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(WorkOrderStop::class, 'work_order_stop_id');
    }

    public function effectiveFromBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'effective_from_batch_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_id');
    }

    /** The configuration version this request produced, once applied. */
    public function resultingSnapshot(): HasOne
    {
        return $this->hasOne(WorkOrderSnapshot::class, 'change_request_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ChangeRequestStatus::Draft->value,
            ChangeRequestStatus::Submitted->value,
            ChangeRequestStatus::Approved->value,
        ]);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isApplied(): bool
    {
        return $this->status === ChangeRequestStatus::Applied;
    }

    /**
     * Only the fields this request actually touches, keyed as on the work order.
     *
     * Named `proposedChanges` rather than `changes` because Eloquent already owns the
     * dirty-attribute vocabulary on a model (`getChanges()`, `hasChanges()`,
     * `wasChanged()`), and a proposal is a different thing from an unsaved edit.
     */
    public function proposedChanges(): array
    {
        return array_intersect_key($this->proposed ?? [], array_flip(self::CHANGEABLE_FIELDS));
    }

    /** Whether the request changes anything at all — an empty one must not be submitted. */
    public function hasProposedChanges(): bool
    {
        return $this->proposedChanges() !== [];
    }

    /**
     * Field-by-field diff for the UI and the API, using the before-state captured at
     * apply time when it exists and the live order otherwise.
     *
     * @return array<int, array{field: string, from: mixed, to: mixed}>
     */
    public function diff(): array
    {
        $previous = $this->previous_values ?? [];

        return collect($this->proposedChanges())
            ->map(fn ($to, $field) => [
                'field' => $field,
                'from' => $previous[$field] ?? $this->currentValueOf($field),
                'to' => $to,
            ])
            ->values()
            ->all();
    }

    /**
     * The order's current value for a changeable field.
     *
     * Two of them are not columns — the BOM selection is a pivot and production notes
     * live inside `extra_data` — so plain attribute access returns null for exactly
     * the structural change a reviewer most needs to see a "before" for.
     */
    public function currentValueOf(string $field): mixed
    {
        $workOrder = $this->workOrder;

        if ($workOrder === null) {
            return null;
        }

        return match ($field) {
            'bom_template_ids' => $workOrder->bomTemplates()->pluck('process_templates.id')->all(),
            'production_notes' => $workOrder->extra_data['production_notes'] ?? null,
            'due_date' => $workOrder->due_date?->toIso8601String(),
            default => $workOrder->{$field},
        };
    }
}
