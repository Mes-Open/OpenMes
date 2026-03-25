<?php

namespace App\Services\Analytics;

use App\Models\Workstation;
use App\Models\DowntimeEvent;
use App\Models\MachineEvent;
use App\Models\CycleTimeLog;

class FaultIntelligenceService
{
    /**
     * Calculate Mean Time Between Failures (MTBF) for a workstation.
     */
    public function calculateMtbf(Workstation $workstation, $startDate, $endDate): float
    {
        $faults = MachineEvent::where('workstation_id', $workstation->id)
            ->where('event_type', 'FAULT')
            ->count();

        if ($faults === 0) {
            return 0;
        }

        $totalRunTimeSecs = CycleTimeLog::where('workstation_id', $workstation->id)
            ->sum('cycle_time_secs');

        return (float)($totalRunTimeSecs / $faults);
    }

    /**
     * Calculate Mean Time To Repair (MTTR) for a workstation.
     */
    public function calculateMttr(Workstation $workstation, $startDate, $endDate): float
    {
        $downtimeEvents = DowntimeEvent::where('workstation_id', $workstation->id)
            ->where('downtime_category', 'Unplanned')
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get();

        if ($downtimeEvents->isEmpty()) {
            return 0;
        }

        $totalDowntimeMins = $downtimeEvents->sum('duration_minutes');

        return (float)($totalDowntimeMins / $downtimeEvents->count());
    }

    /**
     * Classify fault frequency and severity to provide root cause analysis.
     */
    public function getFaultClassification(Workstation $workstation): array
    {
        return MachineEvent::where('workstation_id', $workstation->id)
            ->where('event_type', 'FAULT')
            ->selectRaw("payload->>'fault_code' as code, count(*) as frequency")
            ->groupBy('code')
            ->orderBy('frequency', 'desc')
            ->get()
            ->toArray();
    }
}
