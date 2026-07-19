<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\Production\ProductionCostService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Production Cost report for the mobile app, mirroring the web
 * ProductionCostReportController (Pages/admin/cost-reports): per finished work
 * order it aggregates material + labor + additional cost into a total and a
 * cost-per-unit, plus a blended summary. Reuses ProductionCostService so the
 * numbers match the web report. Filters: start_date / end_date / line_id
 * (against completed_at), consistent with the other /api/v1/reports endpoints.
 */
class ProductionCostReportController extends Controller
{
    /** Cap on rows costed for the summary (matches the web report's safety cap). */
    private const CAP = 2000;

    public function __construct(private ProductionCostService $costs) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
        ]);

        $from = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : today()->subDays(29)->startOfDay();
        $to = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : today()->endOfDay();
        $lineId = $validated['line_id'] ?? null;

        $orders = WorkOrder::query()
            ->whereIn('status', WorkOrder::TERMINAL_STATUSES)
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<=', $to)
            ->when($lineId, fn ($q) => $q->where('line_id', $lineId))
            ->with($this->costRelations())
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(self::CAP)
            ->get()
            ->map(fn (WorkOrder $wo) => $this->listRow($wo));

        $material = $orders->sum('material_cost');
        $labor = $orders->sum('labor_cost');
        $additional = $orders->sum('additional_cost');
        $total = $orders->sum('total_cost');
        $qty = $orders->sum('produced_qty');

        $summary = [
            'orders' => $orders->count(),
            'material_cost' => round($material, 2),
            'labor_cost' => round($labor, 2),
            'additional_cost' => round($additional, 2),
            'total_cost' => round($total, 2),
            'avg_cost_per_unit' => $qty > 0 ? round($total / $qty, 4) : null,
            'currency' => $this->costs->defaultCurrency(),
            'mixed_currency' => (bool) $orders->contains('mixed_currency', true),
            'limited' => $orders->count() >= self::CAP,
        ];

        return response()->json([
            'data' => [
                'period' => ['start' => $from->toDateString(), 'end' => $to->toDateString()],
                'summary' => $summary,
                'orders' => $orders->take(50)->values(),
                'currency' => $this->costs->defaultCurrency(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function costRelations(): array
    {
        return [
            'line:id,name',
            'productType:id,name,code',
            'productType.processTemplates.bomItems.material',
            'materialAllocations.material:id,code,name,unit_price,price_currency',
            'employeeActivities.worker.wageGroup',
            'additionalCosts',
        ];
    }

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
}
