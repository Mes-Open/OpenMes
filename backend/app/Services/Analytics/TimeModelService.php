<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use App\Models\Workstation;
use App\Models\DowntimeEvent;
use App\Models\ProductionCycle;
use App\Models\Shift;

class TimeModelService
{
    /**
     * Calculate manufacturing time hierarchy for a given range and workstation.
     */
    public function calculateTimeModel(Workstation $workstation, Carbon $startDate, Carbon $endDate, ?int $shiftId = null): array
    {
        $calendarTime = $startDate->diffInSeconds($endDate);

        // 1. Planned Production Time = Calendar Time - Planned Downtime (Breaks, Scheduled Maintenance)
        $plannedDowntime = $this->getDowntime($workstation, $startDate, $endDate, 'Planned');
        $plannedProductionTime = max(0, $calendarTime - $plannedDowntime);

        // 2. Operating Time = Planned Production Time - Unplanned Downtime (Breakdowns, Setup)
        $unplannedDowntime = $this->getDowntime($workstation, $startDate, $endDate, 'Unplanned');
        $operatingTime = max(0, $plannedProductionTime - $unplannedDowntime);

        // 3. Net Run Time = Total Good + Scrap Units * Ideal Cycle Time
        // This is the core industrial OEE formula.
        $productionCycles = $workstation->productionCycles()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get();

        $totalUnits = $productionCycles->count();
        $avgIdealCycle = $productionCycles->avg('ideal_cycle_time_seconds') ?: 0;

        $netRunTime = $totalUnits * $avgIdealCycle;

        // 4. Fully Productive Time = Good Units * Ideal Cycle Time
        $scrapQuantity = $workstation->qualityEvents()
            ->whereIn('event_type', ['SCRAP', 'REJECTED'])
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->sum('quantity');

        $goodUnits = max(0, $totalUnits - $scrapQuantity);
        $fullyProductiveTime = $goodUnits * $avgIdealCycle;

        // Loss Calculations (Six Big Losses)
        $minorStopsTime = $productionCycles->where('is_micro_stop', true)->sum('cycle_time_seconds');

        // Performance Loss = Operating Time - Net Run Time
        // This naturally includes "Idle Gaps" and "Reduced Speed"
        $performanceLoss = max(0, $operatingTime - $netRunTime);

        // Quality Loss = Net Run Time - Fully Productive Time
        $qualityLoss = max(0, $netRunTime - $fullyProductiveTime);

        return [
            'calendar_time_secs' => (float)$calendarTime,
            'planned_production_time_secs' => (float)$plannedProductionTime,
            'operating_time_secs' => (float)$operatingTime,
            'net_run_time_secs' => (float)$netRunTime,
            'fully_productive_time_secs' => (float)$fullyProductiveTime,
            'losses' => [
                'planned_downtime_secs' => (float)$plannedDowntime,
                'unplanned_downtime_secs' => (float)$unplannedDowntime,
                'performance_loss_secs' => (float)$performanceLoss,
                'quality_loss_secs' => (float)$qualityLoss,
                'minor_stops_secs' => (float)$minorStopsTime,
                'idle_gaps_secs' => max(0, $performanceLoss - $minorStopsTime), // Estimation of idle time not captured by cycles or downtime
            ]
        ];
    }

    /**
     * Get downtime duration within a range, handling overlaps and categories.
     */
    protected function getDowntime(Workstation $workstation, Carbon $rangeStart, Carbon $rangeEnd, string $category): int
    {
        $events = DowntimeEvent::where('workstation_id', $workstation->id)
            ->where('downtime_category', $category)
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $query->whereBetween('started_at', [$rangeStart, $rangeEnd])
                      ->orWhereBetween('ended_at', [$rangeStart, $rangeEnd])
                      ->orWhere(function ($q) use ($rangeStart, $rangeEnd) {
                          $q->where('started_at', '<=', $rangeStart)
                            ->where(fn($q2) => $q2->whereNull('ended_at')->orWhere('ended_at', '>=', $rangeEnd));
                      });
            })
            ->get();

        $intervals = [];

        foreach ($events as $event) {
            $start = Carbon::parse($event->started_at)->max($rangeStart);
            $end = ($event->ended_at ? Carbon::parse($event->ended_at) : now())->min($rangeEnd);

            if ($start < $end) {
                $intervals[] = ['start' => $start->timestamp, 'end' => $end->timestamp];
            }
        }

        if (empty($intervals)) return 0;

        usort($intervals, fn($a, $b) => $a['start'] <=> $b['start']);

        $merged = [];
        $current = $intervals[0];

        for ($i = 1; $i < count($intervals); $i++) {
            if ($intervals[$i]['start'] <= $current['end']) {
                $current['end'] = max($current['end'], $intervals[$i]['end']);
            } else {
                $merged[] = $current;
                $current = $intervals[$i];
            }
        }
        $merged[] = $current;

        $totalSeconds = 0;
        foreach ($merged as $interval) {
            $totalSeconds += ($interval['end'] - $interval['start']);
        }

        return $totalSeconds;
    }
}
