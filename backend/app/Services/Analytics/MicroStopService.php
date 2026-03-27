<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use App\Models\Workstation;
use App\Models\ProductionCycle;
use App\Models\MachineEvent;

class MicroStopService
{
    /**
     * Detect and classify minor stops (micro-stoppages) from production cycles and machine event gaps.
     */
    public function detectMicroStops(Workstation $workstation, Carbon $startDate, Carbon $endDate, float $thresholdSeconds = 60.0): array
    {
        $cycles = ProductionCycle::where('workstation_id', $workstation->id)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->orderBy('started_at')
            ->get();

        $microStops = [];
        $totalDuration = 0;

        // 1. Detect from cycle duration thresholds
        foreach ($cycles as $cycle) {
            if ($cycle->cycle_time_seconds > $cycle->ideal_cycle_time_seconds + $thresholdSeconds) {
                $microStops[] = [
                    'type' => 'cycle_extension',
                    'cycle_id' => $cycle->id,
                    'started_at' => $cycle->started_at,
                    'duration_seconds' => $cycle->cycle_time_seconds - $cycle->ideal_cycle_time_seconds,
                    'ideal_cycle_time_seconds' => $cycle->ideal_cycle_time_seconds,
                    'actual_cycle_time_seconds' => $cycle->cycle_time_seconds,
                ];
                $totalDuration += ($cycle->cycle_time_seconds - $cycle->ideal_cycle_time_seconds);

                if (!$cycle->is_micro_stop) {
                    $cycle->update(['is_micro_stop' => true]);
                }
            }
        }

        // 2. Detect from gaps between cycles (Idle Gaps)
        for ($i = 0; $i < count($cycles) - 1; $i++) {
            $currentEnd = Carbon::parse($cycles[$i]->ended_at);
            $nextStart = Carbon::parse($cycles[$i + 1]->started_at);

            $gapSeconds = $currentEnd->diffInSeconds($nextStart);

            if ($gapSeconds > 0 && $gapSeconds < $thresholdSeconds) {
                $microStops[] = [
                    'type' => 'inter_cycle_gap',
                    'started_at' => $currentEnd->toDateTimeString(),
                    'duration_seconds' => $gapSeconds,
                ];
                $totalDuration += $gapSeconds;
            }
        }

        return [
            'count' => count($microStops),
            'total_duration_secs' => $totalDuration,
            'avg_duration_secs' => count($microStops) > 0 ? $totalDuration / count($microStops) : 0,
            'micro_stops' => $microStops,
        ];
    }
}
