<?php

namespace App\Services\Production;

use App\Enums\DowntimeKind;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

class DowntimeService
{
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

        return $downtime->fresh();
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
        $query = ProductionDowntime::where('line_id', $lineId)
            ->whereDate('started_at', $date)
            ->whereHas('reason', fn ($q) => $q->whereIn('kind', DowntimeKind::lossKinds()))
            ->whereNotNull('duration_minutes');

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        }

        return (int) $query->sum('duration_minutes');
    }

    /**
     * Total planned downtime minutes (subtracted from planned time, does not count as A-loss).
     */
    public function getPlannedMinutes(int $lineId, Carbon $date, ?int $shiftId = null): int
    {
        $query = ProductionDowntime::where('line_id', $lineId)
            ->whereDate('started_at', $date)
            ->whereHas('reason', fn ($q) => $q->where('kind', DowntimeKind::Planned->value))
            ->whereNotNull('duration_minutes');

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        }

        return (int) $query->sum('duration_minutes');
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

    private function findCurrentShiftId(Line $line): ?int
    {
        $now = now()->format('H:i:s');

        return Shift::where('line_id', $line->id)
            ->where('is_active', true)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->value('id');
    }
}
