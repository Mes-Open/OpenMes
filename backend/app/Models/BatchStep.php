<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchStep extends Model
{
    use Auditable, HasFactory;
    use SoftDeletesWithAudit;

    const STATUS_PENDING = 'PENDING';

    // Prerequisites met (previous step done/skipped, or first step) but not yet
    // started — the step is "next in line" and the operator may start it. Sits
    // between PENDING (still blocked) and IN_PROGRESS.
    const STATUS_READY = 'READY';

    const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    const STATUS_DONE = 'DONE';

    const STATUS_SKIPPED = 'SKIPPED';

    protected $fillable = [
        'batch_id',
        'step_number',
        'name',
        'instruction',
        'workstation_id',
        'status',
        'is_optional',
        'variant_group',
        'skip_reason',
        'started_at',
        'completed_at',
        'confirmed_at',
        'confirmed_by',
        'started_by_id',
        'completed_by_id',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'is_optional' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'confirmed_at' => 'datetime',
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
     * Get the workstation for this step.
     */
    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
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
     * Material lot consumption events recorded against this step (ISA-95 genealogy).
     */
    public function lotConsumptions(): HasMany
    {
        return $this->hasMany(BatchStepLotConsumption::class);
    }

    /**
     * Documents attached to this step for shop-floor document control. A
     * mandatory, validatable document must be validated before the step can be
     * completed.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(BatchStepDocument::class);
    }

    /** Soft-deleting a step cascades to its attached documents. */
    public function softDeleteCascades(): array
    {
        return [
            [BatchStepDocument::class, 'batch_step_id'],
        ];
    }

    /**
     * Mandatory, validatable documents on this step that have not been validated
     * yet - the documents that block completion. Empty when nothing blocks.
     */
    public function blockingDocuments()
    {
        return $this->documents()->blocking();
    }

    /** Whether an unvalidated mandatory document is holding this step. */
    public function isBlockedByDocuments(): bool
    {
        return $this->blockingDocuments()->exists();
    }

    /**
     * Whether this step's sequence prerequisites are met (so it may move from
     * PENDING to READY): the first step, any step when sequential enforcement is
     * off, or a step whose immediate predecessor is DONE/SKIPPED. Does NOT factor
     * in work-order blocking — that's re-checked at start time.
     */
    public function prerequisitesMet(): bool
    {
        if (! config('openmmes.force_sequential_steps', true)) {
            return true;
        }

        if ($this->step_number === 1) {
            return true;
        }

        $previousStep = $this->batch->steps()
            ->where('step_number', $this->step_number - 1)
            ->first();

        return $previousStep && in_array($previousStep->status, [self::STATUS_DONE, self::STATUS_SKIPPED], true);
    }

    /**
     * Check if this step can be started: it must be READY (the normal case,
     * after promotion) or a still-PENDING step whose prerequisites are already
     * met (safety net for steps created outside the promotion path), the work
     * order must not be blocked. The operator UI only offers Start on READY.
     */
    public function canStart(): bool
    {
        if (! in_array($this->status, [self::STATUS_READY, self::STATUS_PENDING], true)) {
            return false;
        }

        if ($this->batch->workOrder->isBlocked()) {
            return false;
        }

        return $this->prerequisitesMet();
    }

    /**
     * Check if this step can be completed.
     */
    public function canComplete(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Can this step be skipped? Only optional steps or members of a variant
     * group, and only while still pending/in progress.
     */
    public function canSkip(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_READY, self::STATUS_IN_PROGRESS], true)
            && ($this->is_optional || $this->variant_group !== null);
    }

    /** Other steps in the same variant group within this batch (excludes self). */
    public function variantSiblings()
    {
        return $this->batch->steps()
            ->where('variant_group', $this->variant_group)
            ->where('id', '!=', $this->id);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
