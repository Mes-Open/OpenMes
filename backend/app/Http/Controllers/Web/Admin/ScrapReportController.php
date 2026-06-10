<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Scrap\ScrapReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScrapReportController extends Controller
{
    public function __construct(
        protected ScrapReportService $scrapReports,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'line_id'   => ['nullable', 'integer', 'exists:lines,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $lineId = isset($validated['line_id']) ? (int) $validated['line_id'] : null;
        $from = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : today()->subDays(29)->startOfDay();
        $to = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : today()->endOfDay();

        return Inertia::render('admin/scrap-reports/Index', [
            'lines' => Line::orderBy('name')->get(),
            'lineId' => $lineId,
            'dateFrom' => $from->toDateString(),
            'dateTo' => $to->toDateString(),
            'pareto' => $this->scrapReports->pareto($from, $to, $lineId),
            'ratePerLine' => $this->scrapReports->ratePerLine($from, $to),
        ]);
    }
}
