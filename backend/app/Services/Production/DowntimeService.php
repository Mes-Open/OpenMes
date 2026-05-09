<?php

namespace App\Services\Production;

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
        // Find current shift for context
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
     * Get total unplanned downtime minutes for a line on a date.
     */
    public function getUnplannedMinutes(int $lineId, Carbon $date, ?int $shiftId = null): int
    {
        $query = ProductionDowntime::where('line_id', $lineId)
            ->whereDate('started_at', $date)
            ->whereHas('reason', fn ($q) => $q->where('is_planned', false))
            ->whereNotNull('duration_minutes');

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        }

        return (int) $query->sum('duration_minutes');
    }

    /**
     * Get total planned downtime minutes for a line on a date.
     */
    public function getPlannedMinutes(int $lineId, Carbon $date, ?int $shiftId = null): int
    {
        $query = ProductionDowntime::where('line_id', $lineId)
            ->whereDate('started_at', $date)
            ->whereHas('reason', fn ($q) => $q->where('is_planned', true))
            ->whereNotNull('duration_minutes');

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        }

        return (int) $query->sum('duration_minutes');
    }

    /**
     * Get downtimes for a line grouped by reason (for reports).
     */
    public function getByReason(int $lineId, Carbon $dateFrom, Carbon $dateTo): array
    {
        return ProductionDowntime::where('line_id', $lineId)
            ->whereBetween('started_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->whereNotNull('duration_minutes')
            ->with('reason')
            ->get()
            ->groupBy('downtime_reason_id')
            ->map(fn ($group) => [
                'reason' => $group->first()->reason?->name ?? 'Unknown',
                'code' => $group->first()->reason?->code ?? 'unknown',
                'is_planned' => $group->first()->reason?->is_planned ?? false,
                'count' => $group->count(),
                'total_minutes' => $group->sum('duration_minutes'),
            ])
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
