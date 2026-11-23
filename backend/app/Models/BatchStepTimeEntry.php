<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One interval of operator (labor) or machine time spent on a batch step (#400).
 *
 * Labor entries belong to a user, machine entries to a workstation; the two
 * kinds are independent, so a machine entry may run while its operator has no
 * open labor entry on the step at all (unattended operation). Totals are kept
 * on the step by StepTimeService.
 */
class BatchStepTimeEntry extends Model
{
    use Auditable;
    use SoftDeletesWithAudit;

    public const KIND_LABOR = 'labor';

    public const KIND_MACHINE = 'machine';

    public const KINDS = [self::KIND_LABOR, self::KIND_MACHINE];

    /** Clocked on/off by the operator at a terminal or over the API. */
    public const SOURCE_OPERATOR = 'operator';

    /** Opened and closed by the workstation's RUNNING state from connectivity. */
    public const SOURCE_MACHINE = 'machine';

    /** Entered afterwards by a supervisor. */
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'batch_step_id',
        'rework_operation_id',
        'kind',
        'user_id',
        'workstation_id',
        'started_at',
        'ended_at',
        'source',
        'note',
        'created_by_id',
        'ended_by_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    /** Set when the time was spent reworking pieces (#400) — it counts on the rework. */
    public function reworkOperation(): BelongsTo
    {
        return $this->belongsTo(ReworkOperation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /** Seconds in the interval; an open entry runs up to $until (default now). */
    public function durationSeconds(?Carbon $until = null): int
    {
        $end = $this->ended_at ?? $until ?? now();

        return max(0, (int) $this->started_at->diffInSeconds($end, false));
    }
}
