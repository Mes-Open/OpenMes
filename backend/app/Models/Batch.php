<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use Auditable, HasCustomFields, HasFactory;
    use SoftDeletesWithAudit;

    const STATUS_PENDING = 'PENDING';

    const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    const STATUS_DONE = 'DONE';

    const STATUS_CANCELLED = 'CANCELLED';

    const LOT_ON_START = 'on_start';

    const LOT_ON_RELEASE = 'on_release';

    const RELEASE_FOR_PRODUCTION = 'for_production';

    const RELEASE_FOR_SALE = 'for_sale';

    protected $fillable = [
        'work_order_id',
        'batch_number',
        'lot_number',
        'lot_assigned_at',
        'workstation_id',
        'target_qty',
        'produced_qty',
        'status',
        'started_at',
        'completed_at',
        'expiry_date',
        'released_at',
        'released_by',
        'release_type',
        'udi_code',
        'scrap_qty',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'batch_number' => 'integer',
            'target_qty' => 'decimal:2',
            'produced_qty' => 'decimal:2',
            'scrap_qty' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'released_at' => 'datetime',
            'expiry_date' => 'date',
        ];
    }

    /**
     * Get the work order that owns this batch.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the steps for this batch.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(BatchStep::class)->orderBy('step_number');
    }

    /**
     * Check if all steps are complete. Every step must be DONE or SKIPPED, and
     * each variant group must have one executed (DONE) step — a fully-skipped
     * group means no variant was chosen, so the batch isn't finished.
     */
    public function allStepsComplete(): bool
    {
        $pending = $this->steps()
            ->whereNotIn('status', [BatchStep::STATUS_DONE, BatchStep::STATUS_SKIPPED])
            ->exists();

        if ($pending) {
            return false;
        }

        $variantSteps = $this->steps()->whereNotNull('variant_group')->get(['variant_group', 'status']);

        foreach ($variantSteps->groupBy('variant_group') as $rows) {
            if (! $rows->contains(fn ($s) => $s->status === BatchStep::STATUS_DONE)) {
                return false; // a variant group with nothing executed
            }
        }

        return true;
    }

    public function processConfirmations(): HasMany
    {
        return $this->hasMany(ProcessConfirmation::class)->orderByDesc('confirmed_at');
    }

    public function qualityChecks(): HasMany
    {
        return $this->hasMany(QualityCheck::class)->orderByDesc('checked_at');
    }

    public function packagingChecklist()
    {
        return $this->hasOne(PackagingChecklist::class);
    }

    /** Pallets packed from this batch (one batch per pallet). */
    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    /**
     * Material lots produced by this batch (semi-finished / multi-stage output).
     * The inverse of MaterialLot::sourceBatch().
     */
    public function outputLots(): HasMany
    {
        return $this->hasMany(MaterialLot::class, 'source_batch_id');
    }

    /**
     * Promote every PENDING step whose sequence prerequisites are now met to
     * READY ("next in line"). Idempotent — safe to call after any step
     * transition or right after a batch's steps are created.
     */
    public function promoteReadySteps(): void
    {
        $this->steps()
            ->where('status', BatchStep::STATUS_PENDING)
            ->orderBy('step_number')
            ->get()
            ->each(function (BatchStep $step) {
                if ($step->prerequisitesMet()) {
                    $step->update(['status' => BatchStep::STATUS_READY]);
                }
            });
    }

    /**
     * Get the current (in progress or next ready/pending) step.
     */
    public function currentStep()
    {
        // First check for in-progress step
        $inProgress = $this->steps()
            ->where('status', BatchStep::STATUS_IN_PROGRESS)
            ->first();

        if ($inProgress) {
            return $inProgress;
        }

        // Otherwise the next actionable step — READY first, then PENDING.
        return $this->steps()
            ->whereIn('status', [BatchStep::STATUS_READY, BatchStep::STATUS_PENDING])
            ->orderByRaw("CASE status WHEN '".BatchStep::STATUS_READY."' THEN 0 ELSE 1 END")
            ->orderBy('step_number')
            ->first();
    }

    /**
     * Get the workstation assigned to this batch.
     */
    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    /**
     * Get the user who released this batch.
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    public function canRelease(): bool
    {
        return $this->status === self::STATUS_DONE && ! $this->isReleased();
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeReleased($query)
    {
        return $query->whereNotNull('released_at');
    }

    public function scopeUnreleased($query)
    {
        return $query->whereNull('released_at')->where('status', self::STATUS_DONE);
    }

    public function scopeForWorkstation($query, int $workstationId)
    {
        return $query->where('workstation_id', $workstationId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\BatchStep::class, 'batch_id'],
        ];
    }
}
