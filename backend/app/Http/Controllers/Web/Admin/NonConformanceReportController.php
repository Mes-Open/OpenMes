<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Quality\NonConformanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NonConformanceReportController extends Controller
{
    public function __construct(
        protected NonConformanceReportService $reports,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $from = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : today()->subDays(29)->startOfDay();
        $to = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : today()->endOfDay();

        return Inertia::render('admin/non-conformance/Index', [
            'dateFrom' => $from->toDateString(),
            'dateTo' => $to->toDateString(),
            'pareto' => $this->reports->pareto($from, $to),
            'dispositionSummary' => $this->reports->dispositionSummary($from, $to),
            'overdueActions' => $this->reports->overdueActionsCount(),
        ]);
    }
}
