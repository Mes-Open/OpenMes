<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use App\Models\Workstation;
use App\Models\Shift;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;

class ProductionIntelligenceService
{
    public function __construct(protected TimeModelService $timeModelService) {}

    /**
     * Calculate OEE, OOE, and TEEP for a specific workstation and time range.
     */
    public function calculateKpis(Workstation $workstation, Carbon $startDate, Carbon $endDate, ?int $shiftId = null): array
    {
        // Round timestamps for cache hits
        $roundedStart = $startDate->startOfMinute()->toDateTimeString();
        $roundedEnd = $endDate->startOfMinute()->toDateTimeString();
        $cacheKey = "pi_kpis_{$workstation->id}_{$roundedStart}_{$roundedEnd}_{$shiftId}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($workstation, $startDate, $endDate, $shiftId) {
            $timeData = $this->timeModelService->calculateTimeModel($workstation, $startDate, $endDate, $shiftId);

            // Availability = Operating Time / Planned Production Time
            $availability = $timeData['planned_production_time_secs'] > 0
                ? $timeData['operating_time_secs'] / $timeData['planned_production_time_secs']
                : 0;

            // Performance = Net Run Time / Operating Time
            $performance = $timeData['operating_time_secs'] > 0
                ? $timeData['net_run_time_secs'] / $timeData['operating_time_secs']
                : 0;

            // Quality = Fully Productive Time / Net Run Time
            $quality = $timeData['net_run_time_secs'] > 0
                ? $timeData['fully_productive_time_secs'] / $timeData['net_run_time_secs']
                : 0;

            // Standard OEE = Availability * Performance * Quality
            $oee = $availability * $performance * $quality;

            // OOE = Fully Productive Time / Planned Production Time
            $ooe = $timeData['planned_production_time_secs'] > 0
                ? $timeData['fully_productive_time_secs'] / $timeData['planned_production_time_secs']
                : 0;

            // TEEP = Fully Productive Time / Calendar Time
            $teep = $timeData['calendar_time_secs'] > 0
                ? $timeData['fully_productive_time_secs'] / $timeData['calendar_time_secs']
                : 0;

            return [
                'oee' => (float)max(0, min(1, $oee)),
                'ooe' => (float)max(0, min(1, $ooe)),
                'teep' => (float)max(0, min(1, $teep)),
                'availability' => (float)max(0, min(1, $availability)),
                'performance' => (float)max(0, min(1, $performance)),
                'quality' => (float)max(0, min(1, $quality)),
                'time_model' => $timeData,
                'calculated_at' => now()->toDateTimeString(),
            ];
        });
    }
}
