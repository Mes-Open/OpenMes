<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceSchedule extends Model
{
    use HasFactory, Auditable;

    public const FREQ_DAILY     = 'daily';
    public const FREQ_WEEKLY    = 'weekly';
    public const FREQ_MONTHLY   = 'monthly';
    public const FREQ_QUARTERLY = 'quarterly';
    public const FREQ_ANNUALLY  = 'annually';
    public const FREQ_BY_HOURS  = 'by_hours';

    public const FREQUENCIES = [
        self::FREQ_DAILY,
        self::FREQ_WEEKLY,
        self::FREQ_MONTHLY,
        self::FREQ_QUARTERLY,
        self::FREQ_ANNUALLY,
        self::FREQ_BY_HOURS,
    ];

    protected $fillable = [
        'name',
        'description',
        'tool_id',
        'line_id',
        'workstation_id',
        'event_type',
        'assigned_to_id',
        'cost_source_id',
        'frequency',
        'interval_value',
        'preferred_time',
        'lead_time_days',
        'last_executed_at',
        'next_due_at',
        'is_active',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'last_executed_at' => 'datetime',
            'next_due_at'      => 'datetime',
            'is_active'        => 'boolean',
            'interval_value'   => 'integer',
            'lead_time_days'   => 'integer',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function costSource(): BelongsTo
    {
        return $this->belongsTo(CostSource::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MaintenanceEvent::class, 'schedule_id');
    }

    // ── Logic ──────────────────────────────────────────────────────────────

    /**
     * Whether this schedule is due to generate an event right now, considering
     * lead_time_days. Inactive schedules are never due.
     */
    public function isDue(): bool
    {
        if (! $this->is_active || $this->next_due_at === null) {
            return false;
        }

        return $this->next_due_at->lte(now()->addDays($this->lead_time_days));
    }

    /**
     * Advance next_due_at by one cycle and record last_executed_at.
     */
    public function advance(): void
    {
        $base = $this->next_due_at?->copy() ?? now();

        $next = match ($this->frequency) {
            self::FREQ_DAILY     => $base->copy()->addDays($this->interval_value),
            self::FREQ_WEEKLY    => $base->copy()->addWeeks($this->interval_value),
            self::FREQ_MONTHLY   => $base->copy()->addMonths($this->interval_value),
            self::FREQ_QUARTERLY => $base->copy()->addMonths(3 * $this->interval_value),
            self::FREQ_ANNUALLY  => $base->copy()->addYears($this->interval_value),
            self::FREQ_BY_HOURS  => $base->copy()->addHours($this->interval_value),
            default              => $base->copy()->addDays($this->interval_value),
        };

        if ($this->preferred_time) {
            $t = Carbon::parse($this->preferred_time);
            $next = $next->setTime($t->hour, $t->minute);
        }

        $this->next_due_at      = $next;
        $this->last_executed_at = now();
        $this->save();
    }
}
