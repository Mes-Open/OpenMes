<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use App\Models\Workstation;
use App\Models\Tool;
use App\Models\Worker;
use App\Models\ProductionCycle;
use App\Models\QualityEvent;
use Illuminate\Support\Facades\DB;

class IndustrialPerformanceService
{
    /**
     * Analyze tool usage and estimate wear based on cycle count and time.
     */
    public function analyzeToolPerformance(Tool $tool, Carbon $startDate, Carbon $endDate): array
    {
        $cycles = ProductionCycle::where('tool_id', $tool->id)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get();

        $totalCycles = $cycles->count();
        $totalUsageSeconds = $cycles->sum('cycle_time_seconds');

        $wearDelta = $tool->max_cycles > 0 ? ($totalCycles / $tool->max_cycles) * 100 : 0;

        // Link to downtime events related to this tool
        $toolDowntime = \App\Models\DowntimeEvent::where('tool_id', $tool->id)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->sum('duration_minutes');

        return [
            'tool_id' => $tool->id,
            'tool_code' => $tool->code,
            'total_cycles' => $totalCycles,
            'total_usage_secs' => $totalUsageSeconds,
            'wear_delta_pct' => round($wearDelta, 2),
            'current_wear_pct' => round($tool->wear_percentage, 2),
            'downtime_minutes' => $toolDowntime,
            'cycles_since_last_maintenance' => $tool->current_cycles,
        ];
    }

    /**
     * Compare performance metrics across different operators.
     */
    public function analyzeOperatorPerformance(Carbon $startDate, Carbon $endDate, ?int $lineId = null): array
    {
        $workers = Worker::when($lineId, fn($q) => $q->whereHas('workstation', fn($w) => $w->where('line_id', $lineId)))
            ->get();

        $performance = $workers->map(function ($worker) use ($startDate, $endDate) {
            $cycles = ProductionCycle::where('worker_id', $worker->id)
                ->whereBetween('started_at', [$startDate, $endDate])
                ->get();

            $qualityEvents = QualityEvent::where('worker_id', $worker->id)
                ->whereBetween('occurred_at', [$startDate, $endDate])
                ->get();

            $totalProduced = $cycles->count();
            $scrapCount = $qualityEvents->where('event_type', 'SCRAP')->sum('quantity');
            $qualityRate = $totalProduced > 0 ? (($totalProduced - $scrapCount) / $totalProduced) * 100 : 0;

            $idealTime = $cycles->sum('ideal_cycle_time_seconds');
            $actualTime = $cycles->sum('cycle_time_seconds');
            $efficiency = $actualTime > 0 ? ($idealTime / $actualTime) * 100 : 0;

            return [
                'worker_id' => $worker->id,
                'worker_name' => $worker->name,
                'total_produced' => $totalProduced,
                'scrap_count' => $scrapCount,
                'quality_rate_pct' => round($qualityRate, 2),
                'efficiency_pct' => round($efficiency, 2),
            ];
        });

        return $performance->toArray();
    }
}
