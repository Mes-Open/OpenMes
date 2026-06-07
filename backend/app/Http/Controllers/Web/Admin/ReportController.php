<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

/**
 * Work Order History — a read-only historical report over finished work
 * orders (DONE / CANCELLED / REJECTED). Pure analysis surface: nothing here
 * mutates or purges data, so the full execution record (batches, steps, LOTs,
 * material genealogy, quality) is retained indefinitely and stays queryable.
 */
class ReportController extends Controller
{
    /** Date-range presets applied to completed_at. */
    private const PRESETS = ['today', 'yesterday', 'last7', 'last30', 'this_month', 'last_month', 'custom', 'all'];

    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);

        $orders = $this->baseQuery($filters)
            ->with([
                'line:id,name',
                'productType:id,name,code',
                'batches:id,work_order_id,lot_number',
            ])
            ->withMin('batches as first_started_at', 'started_at')
            ->withCount('issues')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (WorkOrder $wo) => $this->listRow($wo));

        return Inertia::render('admin/reports/Index', [
            'orders' => $orders,
            'summary' => $this->summary($filters),
            'filters' => $filters,
            'lines' => Line::orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::orderBy('name')->get(['id', 'name']),
            'statusOptions' => WorkOrder::TERMINAL_STATUSES,
            'presets' => self::PRESETS,
        ]);
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load([
            'line:id,name',
            'productType:id,name,code',
            'batches.workstation:id,name',
            'batches.releasedBy:id,name,username',
            'batches.steps' => fn ($q) => $q->orderBy('step_number'),
            'batches.steps.startedBy:id,name,username',
            'batches.steps.completedBy:id,name,username',
            'batches.steps.workstation:id,name',
            'batches.steps.lotConsumptions.materialLot:id,lot_number,material_id',
            'batches.steps.lotConsumptions.materialLot.material:id,code,name',
            'batches.qualityChecks.samples',
            'batches.qualityChecks.checkedBy:id,name,username',
            'batches.outputLots:id,lot_number,source_batch_id,material_id',
            'issues.issueType:id,name',
            'issues.reportedBy:id,name,username',
        ]);

        return Inertia::render('admin/reports/Show', [
            'workOrder' => $this->detail($workOrder),
        ]);
    }

    /**
     * CSV of the currently filtered history (no pagination, capped for safety).
     */
    public function export(Request $request)
    {
        $filters = $this->resolveFilters($request);

        $orders = $this->baseQuery($filters)
            ->with(['line:id,name', 'productType:id,name,code', 'batches:id,work_order_id,lot_number'])
            ->withMin('batches as first_started_at', 'started_at')
            ->withCount('issues')
            ->orderByDesc('completed_at')
            ->limit(10000)
            ->get();

        $csv = Csv::row([
            'Order', 'Product', 'Line', 'Status', 'Planned qty', 'Produced qty',
            'Completed at', 'Execution (min)', 'LOTs', 'Issues',
        ]);

        foreach ($orders as $wo) {
            $row = $this->listRow($wo);
            $csv .= Csv::row([
                $row['order_no'],
                $row['product_name'],
                $row['line_name'],
                $row['status'],
                $row['planned_qty'],
                $row['produced_qty'],
                $row['completed_at'],
                $row['execution_minutes'] ?? '',
                implode(' | ', $row['lots']),
                $row['issues_count'],
            ]);
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="work-order-history_'.date('Y-m-d_H-i-s').'.csv"',
        ]);
    }

    // ── Query building ───────────────────────────────────────────────────

    /**
     * Validated, normalized filter state (also echoed back to the UI).
     */
    private function resolveFilters(Request $request): array
    {
        $status = $request->input('status');
        if (! in_array($status, WorkOrder::TERMINAL_STATUSES, true)) {
            $status = null; // all finished
        }

        $preset = in_array($request->input('preset'), self::PRESETS, true)
            ? $request->input('preset')
            : 'last30';

        return [
            'status' => $status,
            'line_id' => $request->filled('line_id') ? (int) $request->input('line_id') : null,
            'product_type_id' => $request->filled('product_type_id') ? (int) $request->input('product_type_id') : null,
            'preset' => $preset,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'search' => trim((string) $request->input('search', '')) ?: null,
        ];
    }

    private function baseQuery(array $filters)
    {
        $q = WorkOrder::query()
            ->whereIn('status', WorkOrder::TERMINAL_STATUSES);

        if ($filters['status']) {
            $q->where('status', $filters['status']);
        }
        if ($filters['line_id']) {
            $q->where('line_id', $filters['line_id']);
        }
        if ($filters['product_type_id']) {
            $q->where('product_type_id', $filters['product_type_id']);
        }

        [$from, $to] = $this->dateBounds($filters);
        if ($from) {
            $q->where('completed_at', '>=', $from);
        }
        if ($to) {
            $q->where('completed_at', '<=', $to);
        }

        if ($filters['search']) {
            $term = $filters['search'];
            $q->where(function ($sub) use ($term) {
                $sub->where('order_no', 'like', "%{$term}%")
                    ->orWhereHas('batches', fn ($b) => $b->where('lot_number', 'like', "%{$term}%"));
            });
        }

        return $q;
    }

    /**
     * Resolve a preset (or custom range) into [from, to] Carbon bounds.
     */
    private function dateBounds(array $filters): array
    {
        $now = Carbon::now();

        return match ($filters['preset']) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last7' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last30' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                $filters['from'] ? Carbon::parse($filters['from'])->startOfDay() : null,
                $filters['to'] ? Carbon::parse($filters['to'])->endOfDay() : null,
            ],
            default => [null, null], // 'all' — full retained history
        };
    }

    // ── Shaping ──────────────────────────────────────────────────────────

    private function listRow(WorkOrder $wo): array
    {
        $execMinutes = ($wo->completed_at && $wo->first_started_at)
            ? max(0, Carbon::parse($wo->first_started_at)->diffInMinutes($wo->completed_at))
            : null;

        return [
            'id' => $wo->id,
            'order_no' => $wo->order_no,
            'product_name' => $wo->productType?->name,
            'product_code' => $wo->productType?->code,
            'line_name' => $wo->line?->name,
            'status' => $wo->status,
            'planned_qty' => (float) $wo->planned_qty,
            'produced_qty' => (float) $wo->produced_qty,
            'completed_at' => $wo->completed_at?->toIso8601String(),
            'execution_minutes' => $execMinutes,
            'lots' => $wo->relationLoaded('batches')
                ? $wo->batches->pluck('lot_number')->filter()->values()->all()
                : [],
            'issues_count' => $wo->issues_count ?? 0,
        ];
    }

    private function summary(array $filters): array
    {
        $base = $this->baseQuery($filters);

        $count = (clone $base)->count();
        $produced = (clone $base)->sum('produced_qty');
        $planned = (clone $base)->sum('planned_qty');

        // On-time = completed on/before due_date (only orders that have a due date).
        $withDue = (clone $base)->whereNotNull('due_date')->whereNotNull('completed_at')->count();
        $onTime = (clone $base)->whereNotNull('due_date')->whereNotNull('completed_at')
            ->whereColumn('completed_at', '<=', 'due_date')->count();

        // Average execution time across the matching orders (minutes).
        $ids = (clone $base)->pluck('id');
        $avgMinutes = null;
        if ($ids->isNotEmpty()) {
            $expr = match (DB::getDriverName()) {
                'pgsql' => 'AVG(EXTRACT(EPOCH FROM (completed_at - started_at)) / 60)',
                'sqlite' => "AVG((strftime('%s', completed_at) - strftime('%s', started_at)) / 60.0)",
                default => 'AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at) / 60)',
            };
            $avgMinutes = DB::table('batches')
                ->whereIn('work_order_id', $ids)
                ->whereNotNull('started_at')
                ->whereNotNull('completed_at')
                ->selectRaw("$expr as v")
                ->value('v');
            $avgMinutes = $avgMinutes !== null ? round((float) $avgMinutes, 1) : null;
        }

        return [
            'orders' => $count,
            'produced' => (float) $produced,
            'planned' => (float) $planned,
            'avg_execution_minutes' => $avgMinutes,
            'on_time_pct' => $withDue > 0 ? round($onTime / $withDue * 100, 1) : null,
        ];
    }

    private function detail(WorkOrder $wo): array
    {
        $snapshot = $wo->process_snapshot ?? [];

        $firstStart = $wo->batches
            ->flatMap->steps
            ->pluck('started_at')
            ->filter()
            ->min();

        return [
            'id' => $wo->id,
            'order_no' => $wo->order_no,
            'status' => $wo->status,
            'description' => $wo->description,
            'line_name' => $wo->line?->name,
            'product' => $wo->productType ? [
                'name' => $wo->productType->name,
                'code' => $wo->productType->code,
            ] : null,
            'planned_qty' => (float) $wo->planned_qty,
            'produced_qty' => (float) $wo->produced_qty,
            'dates' => [
                'created_at' => $wo->created_at?->toIso8601String(),
                'planned_start_at' => $wo->planned_start_at?->toIso8601String(),
                'planned_end_at' => $wo->planned_end_at?->toIso8601String(),
                'due_date' => $wo->due_date?->toIso8601String(),
                'completed_at' => $wo->completed_at?->toIso8601String(),
            ],
            'execution_minutes' => ($wo->completed_at && $firstStart)
                ? max(0, Carbon::parse($firstStart)->diffInMinutes($wo->completed_at))
                : null,
            'on_time' => ($wo->due_date && $wo->completed_at) ? $wo->completed_at <= $wo->due_date : null,
            'template' => [
                'name' => $snapshot['template_name'] ?? null,
                'version' => $snapshot['template_version'] ?? null,
            ],
            'batches' => $wo->batches->map(fn ($b) => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'status' => $b->status,
                'lot_number' => $b->lot_number,
                'lot_assigned_at' => $b->lot_assigned_at,
                'udi_code' => $b->udi_code,
                'expiry_date' => $b->expiry_date?->toIso8601String(),
                'target_qty' => (float) $b->target_qty,
                'produced_qty' => (float) $b->produced_qty,
                'scrap_qty' => (float) $b->scrap_qty,
                'started_at' => $b->started_at?->toIso8601String(),
                'completed_at' => $b->completed_at?->toIso8601String(),
                'released_at' => $b->released_at?->toIso8601String(),
                'released_by' => $b->releasedBy?->name,
                'workstation' => $b->workstation?->name,
                'steps' => $b->steps->map(fn ($s) => [
                    'step_number' => $s->step_number,
                    'name' => $s->name,
                    'status' => $s->status,
                    'started_at' => $s->started_at?->toIso8601String(),
                    'completed_at' => $s->completed_at?->toIso8601String(),
                    'duration_minutes' => $s->duration_minutes,
                    'started_by' => $s->startedBy?->name,
                    'completed_by' => $s->completedBy?->name,
                    'workstation' => $s->workstation?->name,
                    'consumptions' => $s->lotConsumptions->map(fn ($c) => [
                        'material_code' => $c->materialLot?->material?->code,
                        'material_name' => $c->materialLot?->material?->name,
                        'lot_number' => $c->materialLot?->lot_number,
                        'quantity' => (float) $c->quantity_consumed,
                        'consumed_at' => $c->consumed_at?->toIso8601String(),
                    ])->all(),
                ])->all(),
                'quality_checks' => $b->qualityChecks->map(fn ($qc) => [
                    'id' => $qc->id,
                    'all_passed' => (bool) $qc->all_passed,
                    'production_quantity' => $qc->production_quantity,
                    'notes' => $qc->notes,
                    'checked_by' => $qc->checkedBy?->name,
                    'checked_at' => $qc->checked_at?->toIso8601String(),
                    'samples' => $qc->samples->map(fn ($sm) => [
                        'sample_number' => $sm->sample_number,
                        'parameter_name' => $sm->parameter_name,
                        'parameter_type' => $sm->parameter_type,
                        'value_numeric' => $sm->value_numeric,
                        'value_boolean' => $sm->value_boolean,
                        'is_passed' => $sm->is_passed,
                    ])->all(),
                ])->all(),
                'output_lots' => $b->outputLots->map(fn ($l) => [
                    'lot_number' => $l->lot_number,
                ])->all(),
            ])->all(),
            'issues' => $wo->issues->map(fn ($i) => [
                'id' => $i->id,
                'type' => $i->issueType?->name,
                'description' => $i->description,
                'status' => $i->status,
                'is_blocking' => (bool) $i->is_blocking,
                'reported_by' => $i->reportedBy?->name,
                'reported_at' => $i->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
