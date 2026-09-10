<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-(serial unit, step) progression gate (#290). Deliberately parallel to
 * BatchStep, not built on it: BatchStep stays the batch-wide aggregate row
 * that material consumption / quality checks / typed outputs book against,
 * for every batch, unchanged. UnitStep only gates what one physical piece may
 * do next, so pieces in the same batch can be at different steps at once.
 *
 * Status constants intentionally parallel BatchStep's rather than sharing a
 * trait (scope doc decision #3) — revisit if the two drift apart in practice.
 */
class UnitStep extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    const STATUS_PENDING = 'PENDING';

    const STATUS_READY = 'READY';

    const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    const STATUS_DONE = 'DONE';

    const STATUS_SKIPPED = 'SKIPPED';

    protected $fillable = [
        'batch_id',
        'serial_unit_id',
        'batch_step_id',
        'step_number',
        'status',
        'started_at',
        'started_by_id',
        'completed_at',
        'completed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class);
    }

    /** The aggregate BatchStep this row tracks progress against. */
    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /**
     * Sequential enforcement scoped to this serial unit: step 1 is always
     * eligible; step N needs step N-1 DONE or SKIPPED for the *same unit*
     * (siblings on other units are irrelevant — that's the whole point).
     */
    public function prerequisitesMet(): bool
    {
        if ($this->step_number === 1) {
            return true;
        }

        $previous = self::query()
            ->where('serial_unit_id', $this->serial_unit_id)
            ->where('step_number', $this->step_number - 1)
            ->first();

        return $previous && in_array($previous->status, [self::STATUS_DONE, self::STATUS_SKIPPED], true);
    }

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

    public function canComplete(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }
}
