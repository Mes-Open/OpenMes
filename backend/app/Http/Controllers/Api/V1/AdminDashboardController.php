<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\OeeRecord;
use App\Models\WorkOrder;
use App\Services\Production\OeeCalculationService;
use App\Services\Quality\NonConformanceReportService;
use App\Services\Scrap\ScrapReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Admin dashboard data for the mobile/tablet app, mirroring the web Inertia
 * dashboard (Pages/admin/Dashboard.jsx + Web/Admin/DashboardController). The web
 * page gets row data via Electric live-sync and computes KPI stats client-side;
 * here we compute the same aggregates server-side and return them in one call so
 * the mobile screen renders 1:1 without Electric.
 */
class AdminDashboardController extends Controller
{
    private const WO_TERMINAL = [
        WorkOrder::STATUS_DONE,
        WorkOrder::STATUS_CANCELLED,
        WorkOrder::STATUS_REJECTED,
    ];

    public function index(Request $request): JsonResponse
    {
        // Keep today's OEE fresh (cached 15 min) — same side-effect as the web page.
        Cache::remember('oee_calculated_'.today()->toDateString(), 900, function () {
            $svc = app(OeeCalculationService::class);
            $svc->calculateAll(today());
            $svc->calculateAll(Carbon::yesterday());

            return true;
        });

        $lineId = $request->query('line_id');

        // Active work orders (shape excludes terminal statuses), optionally line-scoped.
        $woQuery = WorkOrder::whereNotIn('status', self::WO_TERMINAL);
        if ($lineId) {
            $woQuery->where('line_id', $lineId);
        }
        $workOrders = $woQuery->get();

        // Open issues, scoped to the line's work orders when filtered.
        $issuesQuery = Issue::open();
        if ($lineId) {
            $issuesQuery->whereIn('work_order_id', $workOrders->pluck('id'));
        }
        $issues = $issuesQuery->get();

        $lines = Line::orderBy('name')->get();
        $blockingTypeIds = IssueType::where('is_blocking', true)->pluck('id')
            ->map(fn ($id) => (string) $id)->all();

        $today = today()->toDateString();

        $stats = [
            'total_work_orders' => $workOrders->count(),
            'pending' => $workOrders->where('status', WorkOrder::STATUS_PENDING)->count(),
            'in_progress' => $workOrders->whereIn('status', [WorkOrder::STATUS_ACCEPTED, WorkOrder::STATUS_IN_PROGRESS])->count(),
            'blocked' => $workOrders->where('status', WorkOrder::STATUS_BLOCKED)->count(),
            'active_today' => $workOrders->filter(fn ($wo) => optional($wo->updated_at)->toDateString() === $today)->count(),
            'open_issues' => $issues->count(),
            'blocking_issues' => $issues->filter(fn ($i) => in_array((string) $i->issue_type_id, $blockingTypeIds, true))->count(),
            'active_lines' => $lines->where('is_active', true)->count(),
        ];

        // Today's OEE per line (A/P/Q + OEE %), N/A where no record.
        $oeeToday = OeeRecord::where('record_date', $today)->get()->keyBy('line_id');
        $oee = $lines->map(function (Line $line) use ($oeeToday) {
            $r = $oeeToday->get($line->id);

            return [
                'line_id' => $line->id,
                'line_name' => $line->name,
                // Preserve NULLs — the web dashboard renders N/A on a gray card
                // for a record whose OEE hasn't been computed; casting null to
                // float would turn that into a red 0.0%.
                'oee_pct' => $r && $r->oee_pct !== null ? (float) $r->oee_pct : null,
                'availability_pct' => $r && $r->availability_pct !== null ? (float) $r->availability_pct : null,
                'performance_pct' => $r && $r->performance_pct !== null ? (float) $r->performance_pct : null,
                'quality_pct' => $r && $r->quality_pct !== null ? (float) $r->quality_pct : null,
            ];
        })->values();

        // Recent work orders (10, newest first).
        $lineById = $lines->keyBy('id');
        $recent = $workOrders->sortByDesc('created_at')->take(10)->map(fn (WorkOrder $wo) => [
            'id' => $wo->id,
            'order_no' => $wo->order_no,
            'line_name' => optional($lineById->get($wo->line_id))->name,
            'status' => $wo->status,
            'produced_qty' => (float) $wo->produced_qty,
            'planned_qty' => (float) $wo->planned_qty,
        ])->values();

        // Open issues (5, newest first).
        $typeById = IssueType::all()->keyBy('id');
        $openIssues = $issues->sortByDesc('created_at')->take(5)->map(function (Issue $i) use ($typeById) {
            $type = $typeById->get($i->issue_type_id);

            return [
                'id' => $i->id,
                'title' => $i->title,
                'type_name' => optional($type)->name,
                'is_blocking' => (bool) optional($type)->is_blocking,
                'work_order_id' => $i->work_order_id,
                'status' => $i->status,
            ];
        })->values();

        return response()->json([
            'data' => [
                'stats' => $stats,
                'oee' => $oee,
                'inbound_qc' => $this->inboundQcStats(),
                'materials' => $this->materialsStats(),
                'scrap' => $this->scrapStats(),
                'non_conformance' => $this->nonConformanceStats(),
                'recent_work_orders' => $recent,
                'open_issues' => $openIssues,
                'lines' => $lines->map(fn (Line $l) => ['id' => $l->id, 'name' => $l->name])->values(),
            ],
        ]);
    }

    /** Inbound QC over the trailing 30 days (parity with Web DashboardController). */
    private function inboundQcStats(): array
    {
        $since = now()->subDays(29)->startOfDay();
        $base = Inspection::where('started_at', '>=', $since);
        $completed = (clone $base)->whereIn('status', ['pass', 'fail', 'conditional_pass'])->count();
        $passed = (clone $base)->where('status', 'pass')->count();

        return [
            'pending' => Inspection::where('status', 'pending')->count(),
            'completed_30d' => $completed,
            'failed_30d' => (clone $base)->where('status', 'fail')->count(),
            'conditional_30d' => (clone $base)->where('status', 'conditional_pass')->count(),
            'pass_rate_30d' => $completed > 0 ? round(($passed / $completed) * 100, 1) : null,
        ];
    }

    private function materialsStats(): array
    {
        return [
            'low_stock_count' => Material::where('is_active', true)
                ->whereNotNull('min_stock_level')
                ->whereColumn('stock_quantity', '<=', 'min_stock_level')
                ->count(),
            'expiring_count' => MaterialLot::where('status', MaterialLot::STATUS_RELEASED)
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [today(), today()->addDays(30)])
                ->count(),
            'reserved_total' => (float) Material::sum('reserved_quantity'),
            'lots_total' => MaterialLot::where('status', MaterialLot::STATUS_RELEASED)->count(),
            'quarantined_count' => MaterialLot::where('status', MaterialLot::STATUS_QUARANTINE)->count(),
        ];
    }

    private function scrapStats(): array
    {
        $pareto = app(ScrapReportService::class)->pareto(
            now()->subDays(29)->startOfDay(),
            now()->endOfDay(),
        );
        $top = $pareto['reasons'][0] ?? null;

        return [
            'total_qty_30d' => $pareto['total_qty'],
            'entries_30d' => $pareto['total_entries'],
            'top_reason' => $top['name'] ?? null,
            'top_reason_qty' => $top['qty'] ?? null,
        ];
    }

    private function nonConformanceStats(): array
    {
        $service = app(NonConformanceReportService::class);
        $openByType = $service->openByType();

        return [
            'open_total' => array_sum(array_column($openByType, 'count')),
            'open_by_type' => array_slice($openByType, 0, 5),
            'disposition_summary' => $service->dispositionSummary(),
            'overdue_actions' => $service->overdueActionsCount(),
        ];
    }
}
