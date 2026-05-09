<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\Line;
use App\Models\OeeRecord;
use App\Models\ProcessTemplate;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OeeCalculationService
{
    public function __construct(
        protected DowntimeService $downtimeService,
    ) {}

    /**
     * Calculate and store OEE for a line on a given date.
     * If shift is provided, calculates per-shift; otherwise whole day.
     */
    public function calculateForDate(Line $line, Carbon $date, ?Shift $shift = null): ?OeeRecord
    {
        $plannedMinutes = $this->getPlannedMinutes($line, $date, $shift);

        if ($plannedMinutes <= 0) {
            return null;
        }

        // Downtimes
        $unplannedDowntime = $this->downtimeService->getUnplannedMinutes($line->id, $date, $shift?->id);
        $plannedDowntime = $this->downtimeService->getPlannedMinutes($line->id, $date, $shift?->id);

        // Subtract planned downtime from planned time (planned breaks don't count as losses)
        $netPlannedMinutes = $plannedMinutes - $plannedDowntime;
        $operatingMinutes = max(0, $netPlannedMinutes - $unplannedDowntime);

        // Production data
        $productionData = $this->getProductionData($line, $date, $shift);
        $totalProduced = $productionData['total_produced'];
        $scrapQty = $productionData['scrap_qty'];
        $goodProduced = $totalProduced - $scrapQty;

        // Ideal cycle time
        $idealCycle = $this->getIdealCycleTime($line, $date, $shift);

        // Calculate OEE components
        $availability = $netPlannedMinutes > 0
            ? ($operatingMinutes / $netPlannedMinutes) * 100
            : null;

        $performance = ($operatingMinutes > 0 && $idealCycle > 0)
            ? (($totalProduced * $idealCycle) / $operatingMinutes) * 100
            : null;

        $quality = $totalProduced > 0
            ? ($goodProduced / $totalProduced) * 100
            : null;

        // Cap performance at 100% to avoid nonsensical values
        if ($performance !== null && $performance > 100) {
            $performance = 100;
        }

        $oee = ($availability !== null && $performance !== null && $quality !== null)
            ? ($availability / 100) * ($performance / 100) * ($quality / 100) * 100
            : null;

        // Store/update record
        return OeeRecord::updateOrCreate(
            [
                'line_id' => $line->id,
                'workstation_id' => null,
                'shift_id' => $shift?->id,
                'record_date' => $date->toDateString(),
            ],
            [
                'planned_minutes' => $netPlannedMinutes,
                'operating_minutes' => $operatingMinutes,
                'downtime_minutes' => $unplannedDowntime,
                'ideal_cycle_minutes' => $idealCycle,
                'total_produced' => $totalProduced,
                'good_produced' => $goodProduced,
                'scrap_qty' => $scrapQty,
                'availability_pct' => $availability !== null ? round($availability, 2) : null,
                'performance_pct' => $performance !== null ? round($performance, 2) : null,
                'quality_pct' => $quality !== null ? round($quality, 2) : null,
                'oee_pct' => $oee !== null ? round($oee, 2) : null,
            ]
        );
    }

    /**
     * Calculate OEE for all active lines for a given date.
     */
    public function calculateAll(Carbon $date): array
    {
        $lines = Line::where('is_active', true)->get();
        $records = [];

        foreach ($lines as $line) {
            $shifts = Shift::where('line_id', $line->id)->where('is_active', true)->get();

            if ($shifts->isEmpty()) {
                // No shifts defined → calculate whole day (assume 8h default)
                $record = $this->calculateForDate($line, $date);
                if ($record) {
                    $records[] = $record;
                }
            } else {
                foreach ($shifts as $shift) {
                    $record = $this->calculateForDate($line, $date, $shift);
                    if ($record) {
                        $records[] = $record;
                    }
                }
            }
        }

        return $records;
    }

    /**
     * Get planned production minutes for a line/shift/date.
     */
    private function getPlannedMinutes(Line $line, Carbon $date, ?Shift $shift): int
    {
        if ($shift) {
            return $this->shiftDurationMinutes($shift);
        }

        // If no specific shift, check if line has shifts defined
        $shifts = Shift::where('line_id', $line->id)->where('is_active', true)->get();

        if ($shifts->isNotEmpty()) {
            return $shifts->sum(fn ($s) => $this->shiftDurationMinutes($s));
        }

        // Default: 8 hours
        return 480;
    }

    private function shiftDurationMinutes(Shift $shift): int
    {
        $start = Carbon::parse($shift->start_time);
        $end = Carbon::parse($shift->end_time);

        // Handle overnight shifts
        if ($end->lte($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Get production quantities for a line on a given date/shift.
     */
    private function getProductionData(Line $line, Carbon $date, ?Shift $shift): array
    {
        $query = Batch::whereHas('workOrder', fn ($q) => $q->where('line_id', $line->id))
            ->where('status', Batch::STATUS_DONE)
            ->whereDate('completed_at', $date);

        if ($shift) {
            $startTime = $date->copy()->setTimeFromTimeString($shift->start_time);
            $endTime = $date->copy()->setTimeFromTimeString($shift->end_time);
            if ($endTime->lte($startTime)) {
                $endTime->addDay();
            }
            $query->whereBetween('completed_at', [$startTime, $endTime]);
        }

        $totals = $query->select([
            DB::raw('COALESCE(SUM(produced_qty), 0) as total_produced'),
            DB::raw('COALESCE(SUM(scrap_qty), 0) as scrap_qty'),
        ])->first();

        return [
            'total_produced' => (float) ($totals->total_produced ?? 0),
            'scrap_qty' => (float) ($totals->scrap_qty ?? 0),
        ];
    }

    /**
     * Get ideal cycle time for a line.
     * Priority: 1) Manual on template, 2) Best historical, 3) Estimated from steps
     */
    private function getIdealCycleTime(Line $line, Carbon $date, ?Shift $shift): float
    {
        // Find most common process template used on this line recently
        $templateId = Batch::where('batches.status', Batch::STATUS_DONE)
            ->whereDate('batches.completed_at', '>=', $date->copy()->subDays(30))
            ->join('work_orders', 'batches.work_order_id', '=', 'work_orders.id')
            ->where('work_orders.line_id', $line->id)
            ->whereNotNull('work_orders.process_snapshot')
            ->selectRaw("work_orders.process_snapshot->>'template_id' as tpl_id")
            ->groupBy('tpl_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('tpl_id');

        if ($templateId) {
            $template = ProcessTemplate::find($templateId);

            // 1. Manual ideal cycle time
            if ($template?->ideal_cycle_minutes) {
                return (float) $template->ideal_cycle_minutes;
            }
        }

        // 2. Best historical cycle time (fastest batch in last 30 days)
        $bestCycle = Batch::whereHas('workOrder', fn ($q) => $q->where('line_id', $line->id))
            ->where('status', Batch::STATUS_DONE)
            ->where('produced_qty', '>', 0)
            ->whereDate('completed_at', '>=', $date->copy()->subDays(30))
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('EXTRACT(EPOCH FROM (completed_at - started_at)) / 60.0 / produced_qty AS cycle_time')
            ->orderBy('cycle_time')
            ->limit(1)
            ->value('cycle_time');

        if ($bestCycle && $bestCycle > 0) {
            return round((float) $bestCycle, 4);
        }

        // 3. Fallback: sum of estimated step durations
        if (isset($template)) {
            $totalEstimated = $template->steps()->sum('estimated_duration_minutes');
            if ($totalEstimated > 0) {
                return (float) $totalEstimated;
            }
        }

        // Ultimate fallback — 1 minute (to avoid division by zero)
        return 1.0;
    }
}
