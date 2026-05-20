<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\OeeRecord;
use App\Services\Production\DowntimeService;
use App\Services\Production\OeeCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OeeController extends Controller
{
    public function __construct(
        protected DowntimeService $downtimeService,
        protected OeeCalculationService $oeeService,
    ) {}

    public function index(Request $request)
    {
        $lines = Line::where('is_active', true)->orderBy('name')->get();
        $lineId = $request->query('line_id');
        $dateFrom = $request->query('date_from', today()->subDays(6)->toDateString());
        $dateTo = $request->query('date_to', today()->toDateString());
        $granularity = in_array($request->query('granularity'), ['day', 'week', 'month'], true)
            ? $request->query('granularity')
            : 'day';

        $this->ensureOeeCalculated();

        $query = OeeRecord::with(['line', 'shift'])
            ->whereBetween('record_date', [$dateFrom, $dateTo])
            ->orderByDesc('record_date');

        if ($lineId) {
            $query->where('line_id', $lineId);
        }

        $records = $query->get();

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

        $bucketKey = function ($record) use ($granularity): string {
            $date = Carbon::parse($record->record_date);

            return match ($granularity) {
                'week' => $date->isoFormat('GGGG-[W]WW'),
                'month' => $date->format('Y-m'),
                default => $date->toDateString(),
            };
        };

        // Combined trend (average across all lines per bucket).
        $trend = $records->groupBy($bucketKey)->map(function ($bucket, $key) {
            return [
                'date' => $key,
                'oee' => round($bucket->whereNotNull('oee_pct')->avg('oee_pct') ?? 0, 1),
                'availability' => round($bucket->whereNotNull('availability_pct')->avg('availability_pct') ?? 0, 1),
                'performance' => round($bucket->whereNotNull('performance_pct')->avg('performance_pct') ?? 0, 1),
                'quality' => round($bucket->whereNotNull('quality_pct')->avg('quality_pct') ?? 0, 1),
            ];
        })->sortKeys()->values();

        // Per-line trend (multi-series). Only meaningful when no specific line is filtered.
        $buckets = $records->pluck($bucketKey)->unique()->sort()->values();
        $trendByLine = $lines
            ->filter(fn ($l) => ! $lineId || $l->id == $lineId)
            ->map(function (Line $line) use ($records, $bucketKey, $buckets) {
                $byBucket = $records->where('line_id', $line->id)->groupBy($bucketKey);

                return [
                    'line_id' => $line->id,
                    'line_name' => $line->name,
                    'points' => $buckets->map(fn ($b) => [
                        'date' => $b,
                        'oee' => round($byBucket->get($b, collect())->whereNotNull('oee_pct')->avg('oee_pct') ?? 0, 1),
                    ])->values(),
                ];
            })
            ->values();

        return view('admin.oee.index', compact(
            'lines', 'lineId', 'dateFrom', 'dateTo',
            'records', 'summary', 'trend', 'trendByLine', 'granularity'
        ));
    }

    public function show(Line $line, Request $request)
    {
        $dateFrom = $request->query('date_from', today()->subDays(6)->toDateString());
        $dateTo = $request->query('date_to', today()->toDateString());

        $this->ensureOeeCalculated();

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

    public function print(Request $request)
    {
        $data = $this->gatherPrintData($request);

        return view('admin.oee.print', $data);
    }

    public function printPdf(Request $request)
    {
        $data = $this->gatherPrintData($request);

        $pdf = Pdf::loadView('admin.oee.print-pdf', $data)
            ->setPaper('a4', 'portrait');

        $rangeSlug = $data['dateFrom'] . '_' . $data['dateTo'];
        $scopeSlug = $data['singleLine'] && $data['perLine']->count() === 1
            ? str(($data['perLine']->first()['line']->code ?? 'line'))->slug()
            : 'all-lines';
        $filename = "oee-report-{$scopeSlug}-{$rangeSlug}.pdf";

        return $pdf->download($filename);
    }

    private function gatherPrintData(Request $request): array
    {
        $validated = $request->validate([
            'line_id' => ['nullable', 'integer', 'min:1', 'exists:lines,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $dateFrom = $validated['date_from'] ?? today()->subDays(6)->toDateString();
        $dateTo = $validated['date_to'] ?? today()->toDateString();
        $lineId = $validated['line_id'] ?? null;

        if (Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) > 366) {
            abort(422, 'Date range cannot exceed 366 days.');
        }

        $this->ensureOeeCalculated();

        $linesQuery = Line::where('is_active', true);
        if ($lineId !== null) {
            $linesQuery->where('id', $lineId);
        }
        $lines = $linesQuery->orderBy('name')->get();

        $perLine = $lines->map(function (Line $line) use ($dateFrom, $dateTo) {
            $records = OeeRecord::where('line_id', $line->id)
                ->whereBetween('record_date', [$dateFrom, $dateTo])
                ->with('shift')
                ->orderBy('record_date')
                ->get();

            $downtimeByReason = $this->downtimeService->getByReason(
                $line->id,
                Carbon::parse($dateFrom),
                Carbon::parse($dateTo)
            );

            $summary = [
                'avg_oee' => $records->whereNotNull('oee_pct')->avg('oee_pct'),
                'avg_availability' => $records->whereNotNull('availability_pct')->avg('availability_pct'),
                'avg_performance' => $records->whereNotNull('performance_pct')->avg('performance_pct'),
                'avg_quality' => $records->whereNotNull('quality_pct')->avg('quality_pct'),
                'total_produced' => $records->sum('total_produced'),
                'total_scrap' => $records->sum('scrap_qty'),
                'total_downtime' => $records->sum('downtime_minutes'),
                'days' => $records->count(),
            ];

            return [
                'line' => $line,
                'records' => $records,
                'downtimeByReason' => $downtimeByReason,
                'summary' => $summary,
            ];
        });

        return [
            'perLine' => $perLine,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'singleLine' => $lineId !== null,
            'generatedAt' => now(),
        ];
    }

    /**
     * Auto-calculate OEE for today and yesterday if not already done.
     * Cached for 15 minutes to avoid recalculating on every page load.
     */
    private function ensureOeeCalculated(): void
    {
        Cache::remember('oee_calculated_'.today()->toDateString(), 900, function () {
            $this->oeeService->calculateAll(today());
            $this->oeeService->calculateAll(Carbon::yesterday());

            return true;
        });
    }
}
