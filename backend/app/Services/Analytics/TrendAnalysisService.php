<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use App\Models\Workstation;
use App\Models\Shift;
use Illuminate\Support\Collection;

class TrendAnalysisService
{
    public function __construct(protected ProductionIntelligenceService $piService) {}

    /**
     * Get KPI trends over a period of time, aggregated by hourly, daily, or shift-based buckets.
     */
    public function getTrends(Workstation $workstation, Carbon $startDate, Carbon $endDate, string $interval = 'daily'): array
    {
        $trends = [];
        $current = $startDate->copy();

        if ($interval === 'shift') {
            return $this->getShiftTrends($workstation, $startDate, $endDate);
        }

        while ($current < $endDate) {
            $stepEnd = $this->getStepEnd($current, $interval)->min($endDate);

            $kpis = $this->piService->calculateKpis($workstation, $current, $stepEnd);

            $trends[] = [
                'timestamp' => $current->toDateTimeString(),
                'oee' => round($kpis['oee'] * 100, 2),
                'ooe' => round($kpis['ooe'] * 100, 2),
                'teep' => round($kpis['teep'] * 100, 2),
                'availability' => round($kpis['availability'] * 100, 2),
                'performance' => round($kpis['performance'] * 100, 2),
                'quality' => round($kpis['quality'] * 100, 2),
            ];

            $current = $stepEnd;
        }

        return $trends;
    }

    /**
     * Get KPI trends aggregated by real shift boundaries.
     */
    protected function getShiftTrends(Workstation $workstation, Carbon $startDate, Carbon $endDate): array
    {
        $shifts = Shift::where('is_active', true)
            ->where(fn($q) => $q->where('line_id', $workstation->line_id)->orWhereNull('line_id'))
            ->get();

        $trends = [];
        $current = $startDate->copy();

        while ($current < $endDate) {
            $currentDate = $current->toDateString();

            foreach ($shifts as $shift) {
                $shiftStart = Carbon::parse("{$currentDate} {$shift->start_time}");
                $shiftEnd = Carbon::parse("{$currentDate} {$shift->end_time}");

                // Handle overnight shift
                if ($shiftEnd < $shiftStart) {
                    $shiftEnd->addDay();
                }

                if ($shiftStart >= $endDate) break;

                $rangeStart = $shiftStart->max($startDate);
                $rangeEnd = $shiftEnd->min($endDate);

                if ($rangeStart < $rangeEnd) {
                    $kpis = $this->piService->calculateKpis($workstation, $rangeStart, $rangeEnd, $shift->id);
                    $trends[] = [
                        'timestamp' => $rangeStart->toDateTimeString(),
                        'shift_name' => $shift->name,
                        'oee' => round($kpis['oee'] * 100, 2),
                        'ooe' => round($kpis['ooe'] * 100, 2),
                        'teep' => round($kpis['teep'] * 100, 2),
                        'availability' => round($kpis['availability'] * 100, 2),
                        'performance' => round($kpis['performance'] * 100, 2),
                        'quality' => round($kpis['quality'] * 100, 2),
                    ];
                }
            }
            $current->addDay();
        }

        return $trends;
    }

    /**
     * Helper to get end of interval bucket.
     */
    protected function getStepEnd(Carbon $start, string $interval): Carbon
    {
        return match ($interval) {
            'hourly' => $start->copy()->addHour(),
            'daily' => $start->copy()->addDay(),
            default => $start->copy()->addDay(),
        };
    }
}
