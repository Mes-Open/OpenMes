<?php

namespace App\Services\Analytics;

use App\Models\Workstation;
use Illuminate\Support\Facades\Cache;

class OeeCalculationService
{
    /**
     * Calculate OEE for a specific workstation based on state history and downtime.
     */
    public function calculateOee(Workstation $workstation, $startDate, $endDate): array
    {
        // Round timestamps to the nearest minute to ensure cache hits
        $roundedStart = \Carbon\Carbon::parse($startDate)->startOfMinute()->toDateTimeString();
        $roundedEnd = \Carbon\Carbon::parse($endDate)->startOfMinute()->toDateTimeString();
        $cacheKey = "oee_workstation_{$workstation->id}_{$roundedStart}_{$roundedEnd}";

        return Cache::remember($cacheKey, now()->addMinutes(1), function () use ($workstation, $roundedStart, $roundedEnd) {
            $totalTime = max(1, \Carbon\Carbon::parse($roundedEnd)->diffInSeconds(\Carbon\Carbon::parse($roundedStart)));

            // 1. Availability = (Total Time - Unplanned Downtime) / Total Time
            $unplannedDowntimeSeconds = $workstation->downtimeEvents()
                ->where('downtime_category', 'Unplanned')
                ->where('started_at', '>=', $roundedStart)
                ->where('ended_at', '<=', $roundedEnd)
                ->sum('duration_minutes') * 60;

            $availability = max(0, min(1, ($totalTime - $unplannedDowntimeSeconds) / $totalTime));

            // 2. Performance = Actual Produced / (Planned Rate * Run Time)
            // Simplified: (Run Time / Total Scheduled Run Time)
            $runTimeSeconds = $workstation->states()
                ->where('state', 'RUNNING')
                ->where('started_at', '>=', $roundedStart)
                ->where('ended_at', '<=', $roundedEnd)
                ->sum('duration_seconds');

            $performance = $runTimeSeconds > 0 ? min(1, $runTimeSeconds / max(1, $totalTime - $unplannedDowntimeSeconds)) : 0;

            // 3. Quality = Good Parts / Total Parts
            // In a real MES, this would pull from scrap/reject logs
            $quality = 0.98; // Defaulting to 98% quality as a baseline for calculation

            $oee = $availability * $performance * $quality;

            return [
                'availability' => (float)$availability,
                'performance' => (float)$performance,
                'quality' => (float)$quality,
                'oee' => (float)$oee,
                'calculated_at' => now()->toDateTimeString(),
                'metrics' => [
                    'total_time_secs' => $totalTime,
                    'unplanned_downtime_secs' => $unplannedDowntimeSeconds,
                    'run_time_secs' => $runTimeSeconds,
                ]
            ];
        });
    }

    /**
     * Clear OEE cache for a workstation.
     */
    public function invalidateOeeCache(Workstation $workstation): void
    {
        // Tag-based invalidation if using Redis
        // Cache::tags(['oee', "workstation_{$workstation->id}"])->flush();
    }
}
