<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionCostReportFilterRequest;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Services\Production\ProductionCostService;
use App\Support\Csv;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

/**
 * Production Cost report — read-only costing over finished work orders.
 *
 * Per work order it aggregates material cost (actual consumption, BOM
 * fallback), labor cost (per-worker pay modes) and manual additional costs
 * into a total and a cost-per-unit. Mirrors the Work Order History report's
 * filter/preset/export shape.
 */
class ProductionCostReportController extends Controller
{
    /** Date-range presets applied to completed_at. */
    private const PRESETS = ['today', 'yesterday', 'last7', 'last30', 'this_month', 'last_month', 'custom', 'all'];

    public function __construct(private ProductionCostService $costs) {}

    public function index(ProductionCostReportFilterRequest $request)
    {
        $filters = $this->resolveFilters($request);

        $orders = $this->baseQuery($filters)
            ->with($this->costRelations())
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (WorkOrder $wo) => $this->listRow($wo));

        return Inertia::render('admin/cost-reports/Index', [
            'orders' => $orders,
            'summary' => $this->summary($filters),
            'filters' => $filters,
            'lines' => Line::orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::orderBy('name')->get(['id', 'name']),
            'presets' => self::PRESETS,
            'currency' => $this->costs->defaultCurrency(),
        ]);
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load($this->costRelations());

        return Inertia::render('admin/cost-reports/Show', [
            'breakdown' => $this->costs->breakdown($workOrder),
            'meta' => [
                'product_name' => $workOrder->productType?->name,
                'line_name' => $workOrder->line?->name,
                'status' => $workOrder->status,
                'completed_at' => $workOrder->completed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * CSV of the currently filtered cost report (capped for safety).
     */
    public function export(ProductionCostReportFilterRequest $request)
    {
        $filters = $this->resolveFilters($request);

        $orders = $this->baseQuery($filters)
            ->with($this->costRelations())
            ->orderByDesc('completed_at')
            ->limit(10000)
            ->get();

        $csv = Csv::row([
            'Order', 'Product', 'Line', 'Produced qty', 'Material cost',
            'Labor cost', 'Additional costs', 'Total cost', 'Cost per unit', 'Currency',
        ]);

        foreach ($orders as $wo) {
            $row = $this->listRow($wo);
            $csv .= Csv::row([
                $row['order_no'],
                $row['product_name'],
                $row['line_name'],
                $row['produced_qty'],
                $row['material_cost'],
                $row['labor_cost'],
                $row['additional_cost'],
                $row['total_cost'],
                $row['cost_per_unit'] ?? '',
                $row['currency'],
            ]);
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="production-cost_'.date('Y-m-d_H-i-s').'.csv"',
        ]);
    }

    // ── Query building ───────────────────────────────────────────────────

    /** Relations needed to cost a work order without N+1 queries. */
    private function costRelations(): array
    {
        return [
            'line:id,name',
            'productType:id,name,code',
            'materialAllocations.material:id,code,name,unit_price,price_currency',
            'employeeActivities.worker.wageGroup',
            'additionalCosts',
        ];
    }

    private function resolveFilters(ProductionCostReportFilterRequest $request): array
    {
        $preset = in_array($request->input('preset'), self::PRESETS, true)
            ? $request->input('preset')
            : 'last30';

        return [
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
        $b = $this->costs->breakdown($wo);

        return [
            'id' => $wo->id,
            'order_no' => $b['order_no'],
            'product_name' => $wo->productType?->name,
            'line_name' => $wo->line?->name,
            'produced_qty' => $b['produced_qty'],
            'material_cost' => $b['materials']['total'],
            'labor_cost' => $b['labor']['total'],
            'additional_cost' => $b['additional']['total'],
            'total_cost' => $b['total_cost'],
            'cost_per_unit' => $b['cost_per_unit'],
            'currency' => $b['currency'],
            'mixed_currency' => $b['mixed_currency'],
        ];
    }

    /**
     * Summary cards over the whole filtered set (capped for safety), so the
     * headline totals reflect every matching order, not just the current page.
     */
    private function summary(array $filters): array
    {
        $orders = $this->baseQuery($filters)
            ->with($this->costRelations())
            ->limit(10000)
            ->get();

        $material = 0.0;
        $labor = 0.0;
        $additional = 0.0;
        $total = 0.0;
        $unitCosts = [];
        $mixed = false;

        foreach ($orders as $wo) {
            $row = $this->listRow($wo);
            $material += $row['material_cost'];
            $labor += $row['labor_cost'];
            $additional += $row['additional_cost'];
            $total += $row['total_cost'];
            if ($row['cost_per_unit'] !== null) {
                $unitCosts[] = $row['cost_per_unit'];
            }
            $mixed = $mixed || $row['mixed_currency'];
        }

        return [
            'orders' => $orders->count(),
            'material_cost' => round($material, 2),
            'labor_cost' => round($labor, 2),
            'additional_cost' => round($additional, 2),
            'total_cost' => round($total, 2),
            'avg_cost_per_unit' => count($unitCosts) > 0 ? round(array_sum($unitCosts) / count($unitCosts), 4) : null,
            'currency' => $this->costs->defaultCurrency(),
            'mixed_currency' => $mixed,
        ];
    }
}
