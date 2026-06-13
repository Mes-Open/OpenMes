<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring break window for a crew (e.g. lunch 12:00–12:30 on Mon–Fri).
 * Time-of-day, weekday-scoped; drives WorkerAvailabilityService::isOnBreak().
 */
class CrewBreakWindow extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'crew_id',
        'name',
        'start_time',
        'end_time',
        'days_of_week',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Does this window apply on the given date's weekday (ISO 1=Mon … 7=Sun)? */
    public function appliesOn(CarbonInterface $date): bool
    {
        $days = $this->days_of_week ?? [];

        return in_array((int) $date->format('N'), array_map('intval', $days), true);
    }

    /**
     * Is the given moment inside this window — i.e. the right weekday AND the
     * time-of-day falls in [start_time, end_time)? Windows are intra-day
     * (end_time > start_time is enforced on write), so a plain compare works.
     */
    public function coversTime(CarbonInterface $moment): bool
    {
        if (! $this->appliesOn($moment)) {
            return false;
        }

        $time = $moment->format('H:i:s');

        return $time >= $this->start_time && $time < $this->end_time;
    }
}
