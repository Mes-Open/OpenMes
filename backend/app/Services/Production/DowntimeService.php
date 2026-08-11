<?php

namespace App\Services\Production;

use App\Enums\DowntimeKind;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\User;
use App\Services\Quality\QualityTriggerService;
use App\Support\ShiftWindow;
use Carbon\Carbon;

class DowntimeService
{
    public function __construct(
        private QualityTriggerService $qualityTriggerService,
    ) {}

    /**
     * Start a new downtime event.
     */
    public function start(Line $line, int $reasonId, User $user, ?int $workstationId = null, ?string $notes = null): ProductionDowntime
    {
        $shiftId = $this->findCurrentShiftId($line);

        return ProductionDowntime::create([
            'line_id' => $line->id,
            'workstation_id' => $workstationId,
            'downtime_reason_id' => $reasonId,
            'shift_id' => $shiftId,
            'started_at' => now(),
            'notes' => $notes,
            'reported_by' => $user->id,
        ]);
    }

    /**
     * Stop an active downtime event.
     */
    public function stop(ProductionDowntime $downtime): ProductionDowntime
    {
        $downtime->stop();

        $fresh = $downtime->fresh();

        // Quality-control triggers: after-downtime / after-setup checks (#105).
        $this->qualityTriggerService->fireAfterDowntime($fresh);

        return $fresh;
    }

    /**
     * Get active (unstopped) downtimes for a line.
     */
    public function getActive(int $lineId): ?ProductionDowntime
    {
        return ProductionDowntime::where('line_id', $lineId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    /**
     * Total minutes that count as availability loss (unplanned + changeover).
     */
    public function getLossMinutes(int $lineId, Carbon $date, ?int $shiftId = null): int
    {
        return (int) $this->scopeToShift(
            ProductionDowntime::where('line_id', $lineId)
                ->whereHas('reason', fn ($q) => $q->whereIn('kind', DowntimeKind::lossKinds()))
                ->whereNotNull('duration_minutes'),
            $date,
            $shiftId,
        )->sum('duration_minutes');
    }

    /**
     * Total planned downtime minutes (subtracted from planned time, does not count as A-loss).
     */
    public function getPlannedMinutes(int $lineId, Carbon $date, ?int $shiftId = null): int
    {
        return (int) $this->scopeToShift(
            ProductionDowntime::where('line_id', $lineId)
                ->whereHas('reason', fn ($q) => $q->where('kind', DowntimeKind::Planned->value))
                ->whereNotNull('duration_minutes'),
            $date,
            $shiftId,
        )->sum('duration_minutes');
    }

    /**
     * Narrow a downtime query to one shift occurrence, or to a calendar day when
     * no shift is given.
     *
     * A night shift is two dates. Filtering it by `whereDate('started_at')` puts
     * the hours after midnight on the following day's record — so a fault at
     * 01:30 would be charged to the next occurrence of the same shift, and the
     * night it actually happened would read clean. Scoping to the occurrence's
     * real start and end keeps every stop with the shift that was running.
     */
    private function scopeToShift($query, Carbon $date, ?int $shiftId)
    {
        $shift = $shiftId ? Shift::find($shiftId) : null;

        if (! $shift) {
            return $query->whereDate('started_at', $date);
        }

        $window = ShiftWindow::startingOn($shift, $date);

        return $query->where('shift_id', $shift->id)
            ->where('started_at', '>=', $window->start)
            ->where('started_at', '<', $window->end);
    }

    /**
     * Downtimes for a line grouped by reason (for Pareto reports).
     */
    public function getByReason(int $lineId, Carbon $dateFrom, Carbon $dateTo): array
    {
        return ProductionDowntime::where('line_id', $lineId)
            ->whereBetween('started_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->whereNotNull('duration_minutes')
            ->with('reason')
            ->get()
            ->groupBy('downtime_reason_id')
            ->map(function ($group) {
                $reason = $group->first()->reason;
                $kind = $reason?->kind ?? DowntimeKind::Unplanned;

                return [
                    'reason' => $reason?->name ?? 'Unknown',
                    'code' => $reason?->code ?? 'unknown',
                    'kind' => $kind instanceof DowntimeKind ? $kind->value : (string) $kind,
                    'kind_label' => $kind instanceof DowntimeKind ? $kind->label() : (string) $kind,
                    'kind_color' => $kind instanceof DowntimeKind ? $kind->badgeColor() : 'red',
                    'is_loss' => $kind instanceof DowntimeKind ? $kind->countsAsAvailabilityLoss() : true,
                    'count' => $group->count(),
                    'total_minutes' => $group->sum('duration_minutes'),
                ];
            })
            ->sortByDesc('total_minutes')
            ->values()
            ->toArray();
    }

    /**
     * The shift this line is running right now.
     *
     * Was a `start_time <= now AND end_time >= now` query, which can never match
     * an overnight shift (22:00 is not <= 01:30), ignored `days_of_week`, and
     * ignored plant-wide shifts. Every stop reported during a night therefore
     * carried no shift at all — and since the per-shift queries filter on
     * `shift_id`, those stops fell out of OEE entirely.
     */
    private function findCurrentShiftId(Line $line): ?int
    {
        return ShiftWindow::at($line->id, now())?->shift?->id;
    }
}
