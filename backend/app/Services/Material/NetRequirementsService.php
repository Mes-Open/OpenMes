<?php

namespace App\Services\Material;

use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Basic MRP (#90): explode planned work orders against their BOMs to gross
 * component requirements, net them against on-hand stock and produce a shortage
 * list.
 *
 * Netting runs level by level, not against a flattened leaf explosion. A
 * manufactured subassembly is demand in its own right: its gross requirement is
 * netted against its own stock first, and only the shortfall explodes into the
 * level below. So packs already on the shelf appear as covered demand and pull
 * no media, and a subassembly that runs out is reported by name — a flat leaf
 * explosion could do neither.
 *
 * Demand scope: only PENDING / ACCEPTED work orders — these are planned but not
 * yet started, so no materials have been allocated for them. Started orders
 * (IN_PROGRESS/BLOCKED) have already pulled their materials out of
 * Material.stock_quantity via the allocation engine, so counting them would
 * double-count; their needs are reflected by the lower on-hand instead. On-hand
 * (Material.stock_quantity) is therefore the single, consistent supply figure.
 */
class NetRequirementsService
{
    /** Statuses whose un-started demand MRP plans for. */
    public const DEMAND_STATUSES = [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED];

    /** @var array<int, list<array{material_id: int, required_per_unit: float}>> */
    private array $componentCache = [];

    public function __construct(private readonly BomExplosionService $explosion) {}

    /**
     * @return array{
     *     period: array{start: string, end: string},
     *     line_id: int|null,
     *     requirements: array<int, array<string, mixed>>,
     *     shortages: array<int, array<string, mixed>>,
     *     totals: array{work_orders: int, components: int, shortage_components: int, total_shortfall: float},
     * }
     */
    public function report(Carbon $from, Carbon $to, ?int $lineId = null): array
    {
        $workOrders = WorkOrder::query()
            ->whereIn('status', self::DEMAND_STATUSES)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->when($lineId, fn ($q) => $q->where('line_id', $lineId))
            ->get(['id', 'order_no', 'product_type_id', 'planned_qty', 'line_id', 'due_date']);

        $templates = $this->activeTemplateByProductType($workOrders->pluck('product_type_id')->unique()->filter());

        // Level 1 demand: the direct components of each order's product. Nothing
        // is exploded yet — a subassembly is demand in its own right first.
        $pending = [];        // material_id => qty needed at this level
        $relatedWos = [];     // material_id => [order_no => true]

        foreach ($workOrders as $wo) {
            $template = $templates->get($wo->product_type_id);
            if (! $template) {
                continue;
            }

            foreach ($this->directComponents($template) as $line) {
                $required = round($line['required_per_unit'] * (float) $wo->planned_qty, 4);
                if ($required <= 0) {
                    continue;
                }
                $mid = $line['material_id'];
                $pending[$mid] = ($pending[$mid] ?? 0) + $required;
                $relatedWos[$mid][$wo->order_no] = true;
            }
        }

        if (empty($pending)) {
            return $this->emptyReport($from, $to, $lineId, $workOrders->count());
        }

        $rows = [];        // material_id => requirement row
        $stockLeft = [];   // material_id => on-hand not yet claimed by a higher level
        $level = 0;

        while ($pending !== [] && $level < BomExplosionService::MAX_DEPTH) {
            $materials = Material::with('producingTemplate')
                ->whereIn('id', array_keys($pending))
                ->get()
                ->keyBy('id');

            $next = [];

            foreach ($pending as $materialId => $grossQty) {
                $material = $materials->get($materialId);
                $onHand = (float) ($material?->stock_quantity ?? 0);

                // Stock is claimed once, by whichever level asks first — a
                // material used both directly and inside a subassembly must not
                // spend the same units twice.
                $stockLeft[$materialId] ??= $onHand;
                $fromStock = min($grossQty, max(0.0, $stockLeft[$materialId]));
                $stockLeft[$materialId] -= $fromStock;
                $net = round($grossQty - $fromStock, 4);

                $rows[$materialId] ??= [
                    'material_id' => $materialId,
                    'code' => $material?->code,
                    'name' => $material?->name ?? __('Unknown'),
                    'unit_of_measure' => $material?->unit_of_measure,
                    'required_qty' => 0.0,
                    'available_qty' => round($onHand, 4),
                    'net_qty' => 0.0,
                    'is_short' => false,
                    'is_manufactured' => (bool) ($material?->is_manufactured),
                    'level' => $level,
                    'related_work_orders' => [],
                ];

                $rows[$materialId]['required_qty'] = round($rows[$materialId]['required_qty'] + $grossQty, 4);
                $rows[$materialId]['net_qty'] = round($rows[$materialId]['net_qty'] + $net, 4);
                $rows[$materialId]['is_short'] = $rows[$materialId]['net_qty'] > 0;

                // Only the shortfall has to be made, so only the shortfall
                // explodes. Packs already on the shelf pull no media.
                if ($net > 0 && $material?->isExplodable()) {
                    foreach ($this->directComponents($material->producingTemplate) as $child) {
                        $qty = round($child['required_per_unit'] * $net, 4);
                        if ($qty <= 0) {
                            continue;
                        }
                        $next[$child['material_id']] = ($next[$child['material_id']] ?? 0) + $qty;

                        // The child inherits whatever orders drive its parent.
                        foreach (array_keys($relatedWos[$materialId] ?? []) as $orderNo) {
                            $relatedWos[$child['material_id']][$orderNo] = true;
                        }
                    }
                }
            }

            $pending = $next;
            $level++;
        }

        foreach ($rows as $materialId => $row) {
            $rows[$materialId]['related_work_orders'] = array_keys($relatedWos[$materialId] ?? []);
        }

        $requirements = array_values($rows);

        // Stable, useful ordering: biggest shortfall first, then by name.
        usort($requirements, function ($a, $b) {
            return [$b['net_qty'], $a['name']] <=> [$a['net_qty'], $b['name']];
        });

        $shortages = array_values(array_filter($requirements, fn ($r) => $r['is_short']));

        return [
            'period' => ['start' => $from->toDateString(), 'end' => $to->toDateString()],
            'line_id' => $lineId,
            'requirements' => $requirements,
            'shortages' => $shortages,
            'totals' => [
                'work_orders' => $workOrders->count(),
                'components' => count($requirements),
                'shortage_components' => count($shortages),
                'total_shortfall' => round(array_sum(array_column($shortages, 'net_qty')), 4),
            ],
        ];
    }

    /**
     * The active template (highest version) per product type.
     *
     * @return Collection<int, ProcessTemplate>
     */
    private function activeTemplateByProductType(Collection $productTypeIds): Collection
    {
        if ($productTypeIds->isEmpty()) {
            return collect();
        }

        return ProcessTemplate::whereIn('product_type_id', $productTypeIds)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->get()
            ->groupBy('product_type_id')
            ->map(fn ($rows) => $rows->first());
    }

    /**
     * One template's own BOM lines — one level, not exploded. Quantity carries
     * that line's scrap; deeper scrap is applied by the level that adds it.
     *
     * Product-type lines are plain component references with no material behind
     * them, so the material engine skips them, exactly as the explosion does.
     *
     * @return list<array{material_id: int, required_per_unit: float}>
     */
    private function directComponents(ProcessTemplate $template): array
    {
        $templateId = (int) $template->getKey();

        if (isset($this->componentCache[$templateId])) {
            return $this->componentCache[$templateId];
        }

        $lines = $template->bomItems()
            ->whereNotNull('material_id')
            ->orderBy('sort_order')
            ->get(['material_id', 'quantity_per_unit', 'scrap_percentage'])
            ->map(fn ($item) => [
                'material_id' => (int) $item->material_id,
                'required_per_unit' => round(
                    (float) $item->quantity_per_unit * (1 + ((float) $item->scrap_percentage / 100)),
                    6
                ),
            ])
            ->all();

        return $this->componentCache[$templateId] = $lines;
    }

    private function emptyReport(Carbon $from, Carbon $to, ?int $lineId, int $woCount): array
    {
        return [
            'period' => ['start' => $from->toDateString(), 'end' => $to->toDateString()],
            'line_id' => $lineId,
            'requirements' => [],
            'shortages' => [],
            'totals' => [
                'work_orders' => $woCount,
                'components' => 0,
                'shortage_components' => 0,
                'total_shortfall' => 0.0,
            ],
        ];
    }
}
