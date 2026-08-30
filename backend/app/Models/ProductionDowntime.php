<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionDowntime extends Model
{
    use HasFactory;
    use HasTenant;

    protected $fillable = [
        'line_id',
        'workstation_id',
        'downtime_reason_id',
        'needs_reason',
        'classified_at',
        'classified_by_id',
        'shift_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'notes',
        'reported_by',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'classified_at' => 'datetime',
            'needs_reason' => 'boolean',
            'duration_minutes' => 'integer',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(DowntimeReason::class, 'downtime_reason_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function classifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by_id');
    }

    /**
     * Give an automatically-logged stop its cause. Clears the "needs a cause"
     * flag and records who decided, so the shift log shows judgement rather
     * than the machine's own placeholder.
     */
    public function classify(DowntimeReason $reason, ?User $user = null, ?string $notes = null): void
    {
        $this->update([
            'downtime_reason_id' => $reason->id,
            'needs_reason' => false,
            'classified_at' => now(),
            'classified_by_id' => $user?->id,
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function stop(): void
    {
        $this->update([
            'ended_at' => now(),
            'duration_minutes' => (int) abs(now()->diffInMinutes($this->started_at)),
        ]);
    }
}
