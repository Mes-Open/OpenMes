<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NonConformanceReportRequest;
use App\Services\Quality\NonConformanceReportService;
use Carbon\Carbon;
use Inertia\Inertia;

class NonConformanceReportController extends Controller
{
    public function __construct(
        protected NonConformanceReportService $reports,
    ) {}

    public function index(NonConformanceReportRequest $request)
    {
        $validated = $request->validated();

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
