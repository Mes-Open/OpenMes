<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Material\NetRequirementsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * MRP net requirements & shortage report (#90). Forward-looking planning view:
 * defaults to the next 30 days.
 */
class NetRequirementsReportController extends Controller
{
    public function __construct(
        protected NetRequirementsService $reports,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $lineId = isset($validated['line_id']) ? (int) $validated['line_id'] : null;
        $from = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : today()->startOfDay();
        $to = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : today()->addDays(30)->endOfDay();

        $report = $this->reports->report($from, $to, $lineId);

        return Inertia::render('admin/net-requirements/Index', [
            'lines' => Line::orderBy('name')->get(['id', 'name']),
            'lineId' => $lineId,
            'dateFrom' => $from->toDateString(),
            'dateTo' => $to->toDateString(),
            'requirements' => $report['requirements'],
            'shortages' => $report['shortages'],
            'totals' => $report['totals'],
        ]);
    }
}
