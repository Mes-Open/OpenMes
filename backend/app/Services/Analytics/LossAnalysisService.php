<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use App\Models\Workstation;
use App\Models\DowntimeEvent;
use Illuminate\Support\Facades\DB;

class LossAnalysisService
{
    /**
     * Categorize downtime into the Six Big Losses.
     */
    public function categorizeLosses(Workstation $workstation, Carbon $startDate, Carbon $endDate): array
    {
        $downtimeEvents = DowntimeEvent::where('workstation_id', $workstation->id)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get();

        $losses = [
            'availability_losses' => [
                'breakdowns' => 0,
                'setup_adjustments' => 0,
            ],
            'performance_losses' => [
                'minor_stops' => 0,
                'reduced_speed' => 0,
            ],
            'quality_losses' => [
                'scrap' => 0,
                'rework' => 0,
            ],
        ];

        foreach ($downtimeEvents as $event) {
            $durationSecs = abs(($event->ended_at ? Carbon::parse($event->ended_at) : now())->diffInSeconds(Carbon::parse($event->started_at)));

            // Map anomaly reasons to loss categories if possible
            $category = $event->loss_category ?? $event->downtime_category;

            if ($category === 'Breakdown' || $category === 'Unplanned') {
                $losses['availability_losses']['breakdowns'] += $durationSecs;
            } elseif ($category === 'Setup' || $category === 'Changeover') {
                $losses['availability_losses']['setup_adjustments'] += $durationSecs;
            }
        }

        // Add performance and quality losses from production/quality events
        // This is handled in TimeModelService, but we'll integrate it here for completeness
        $timeModel = (new TimeModelService())->calculateTimeModel($workstation, $startDate, $endDate);

        $losses['performance_losses']['minor_stops'] = $timeModel['losses']['minor_stops_secs'];
        $losses['performance_losses']['reduced_speed'] = $timeModel['losses']['reduced_speed_secs'];
        $losses['quality_losses']['scrap'] = $timeModel['losses']['quality_loss_secs'];

        return $losses;
    }

    /**
     * Generate 80/20 Pareto analysis of downtime causes.
     */
    public function getParetoAnalysis(Workstation $workstation, Carbon $startDate, Carbon $endDate): array
    {
        $causes = DowntimeEvent::where('workstation_id', $workstation->id)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->join('anomaly_reasons', 'downtime_events.anomaly_reason_id', '=', 'anomaly_reasons.id')
            ->select('anomaly_reasons.name', DB::raw('SUM(duration_minutes) as total_duration'))
            ->groupBy('anomaly_reasons.name')
            ->orderBy('total_duration', 'desc')
            ->get();

        $totalDuration = $causes->sum('total_duration');
        $runningTotal = 0;

        $pareto = $causes->map(function ($cause) use (&$runningTotal, $totalDuration) {
            $runningTotal += $cause->total_duration;
            $percentage = $totalDuration > 0 ? ($cause->total_duration / $totalDuration) * 100 : 0;
            $cumulativePercentage = $totalDuration > 0 ? ($runningTotal / $totalDuration) * 100 : 0;

            return [
                'cause' => $cause->name,
                'duration_minutes' => $cause->total_duration,
                'percentage' => round($percentage, 2),
                'cumulative_percentage' => round($cumulativePercentage, 2),
                'is_in_top_80' => $cumulativePercentage <= 80 || ($runningTotal - $cause->total_duration < $totalDuration * 0.8),
            ];
        });

        return $pareto->toArray();
    }
}
