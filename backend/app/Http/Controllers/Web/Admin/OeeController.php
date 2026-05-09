<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\OeeRecord;
use App\Services\Production\DowntimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OeeController extends Controller
{
    public function __construct(
        protected DowntimeService $downtimeService,
    ) {}

    public function index(Request $request)
    {
        $lines = Line::where('is_active', true)->orderBy('name')->get();
        $lineId = $request->query('line_id');
        $dateFrom = $request->query('date_from', today()->subDays(7)->toDateString());
        $dateTo = $request->query('date_to', today()->toDateString());

        $query = OeeRecord::with(['line', 'shift'])
            ->whereBetween('record_date', [$dateFrom, $dateTo])
            ->orderByDesc('record_date');

        if ($lineId) {
            $query->where('line_id', $lineId);
        }

        $records = $query->get();

        // Aggregate per line for summary cards
        $summary = $records->groupBy('line_id')->map(function ($lineRecords) {
            return [
                'avg_oee' => $lineRecords->whereNotNull('oee_pct')->avg('oee_pct'),
                'avg_availability' => $lineRecords->whereNotNull('availability_pct')->avg('availability_pct'),
                'avg_performance' => $lineRecords->whereNotNull('performance_pct')->avg('performance_pct'),
                'avg_quality' => $lineRecords->whereNotNull('quality_pct')->avg('quality_pct'),
                'total_produced' => $lineRecords->sum('total_produced'),
                'total_scrap' => $lineRecords->sum('scrap_qty'),
                'total_downtime' => $lineRecords->sum('downtime_minutes'),
                'days' => $lineRecords->count(),
            ];
        });

        // Trend data for chart (per day)
        $trend = $records->groupBy('record_date')->map(function ($dayRecords, $date) {
            return [
                'date' => $date,
                'oee' => round($dayRecords->whereNotNull('oee_pct')->avg('oee_pct') ?? 0, 1),
                'availability' => round($dayRecords->whereNotNull('availability_pct')->avg('availability_pct') ?? 0, 1),
                'performance' => round($dayRecords->whereNotNull('performance_pct')->avg('performance_pct') ?? 0, 1),
                'quality' => round($dayRecords->whereNotNull('quality_pct')->avg('quality_pct') ?? 0, 1),
            ];
        })->sortKeys()->values();

        return view('admin.oee.index', compact(
            'lines', 'lineId', 'dateFrom', 'dateTo',
            'records', 'summary', 'trend'
        ));
    }

    public function show(Line $line, Request $request)
    {
        $dateFrom = $request->query('date_from', today()->subDays(7)->toDateString());
        $dateTo = $request->query('date_to', today()->toDateString());

        $records = OeeRecord::where('line_id', $line->id)
            ->whereBetween('record_date', [$dateFrom, $dateTo])
            ->with('shift')
            ->orderByDesc('record_date')
            ->get();

        $downtimeByReason = $this->downtimeService->getByReason(
            $line->id,
            Carbon::parse($dateFrom),
            Carbon::parse($dateTo)
        );

        return view('admin.oee.show', compact('line', 'records', 'downtimeByReason', 'dateFrom', 'dateTo'));
    }
}
