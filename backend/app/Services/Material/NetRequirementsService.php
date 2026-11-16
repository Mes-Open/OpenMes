<?php

namespace App\Services\Material;

use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
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
 * (Material.stock_quantity) is therefore the default, single supply figure.
 *
 * Optionally ($includeOpenOrders) the open work orders that make a semi-finished
 * part count as supply of its material too: what is left of the order (planned,
 * less what it has already received into stock) and due by the end of the period.
 * Without it, planning an order for a subassembly and one for its parent asks
 * for the subassembly's components twice.
 */
class NetRequirementsService
{
    /** Statuses whose un-started demand MRP plans for. */
    public const DEMAND_STATUSES = [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED];

    /** Statuses of an order that will still deliver its output. */
    public const SUPPLY_STATUSES = [
        WorkOrder::STATUS_PENDING,
        WorkOrder::STATUS_ACCEPTED,
        WorkOrder::STATUS_IN_PROGRESS,
        WorkOrder::STATUS_BLOCKED,
        WorkOrder::STATUS_PAUSED,
        WorkOrder::STATUS_CHANGE_HOLD,
    ];

    /** @var array<int, list<array{material_id: int, required_per_unit: float}>> */
    private array $componentCache = [];

    public function __construct(private readonly BomExplosionService $explosion) {}

    /**
     * @return array{
     *     period: array{start: string, end: string},
     *     line_id: int|null,
     *     include_open_orders: bool,
     *     requirements: array<int, array<string, mixed>>,
     *     shortages: array<int, array<string, mixed>>,
     *     totals: array{work_orders: int, components: int, shortage_components: int, total_shortfall: float},
     * }
     */
    public function report(Carbon $from, Carbon $to, ?int $lineId = null, bool $includeOpenOrders = false): array
    {
        $workOrders = WorkOrder::query()
            ->whereIn('status', self::DEMAND_STATUSES)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->when($lineId, fn ($q) => $q->where('line_id', $lineId))
            ->with('bomTemplates:process_templates.id')
            ->get(['id', 'order_no', 'product_type_id', 'planned_qty', 'line_id', 'due_date']);

        // Orders without a selected BOM fall back to the newest active template,
        // the same rule the work order itself uses when none is picked.
        $fallback = $this->activeTemplateByProductType(
            $workOrders->filter(fn (WorkOrder $wo) => $wo->bomTemplates->isEmpty())
                ->pluck('product_type_id')->unique()->filter()
        );

        // Level 1 demand: the direct components of each order's product. Nothing
        // is exploded yet — a subassembly is demand in its own right first.
        $pending = [];        // material_id => qty needed at this level
        $relatedWos = [];     // material_id => [order_no => true]

        foreach ($workOrders as $wo) {
            foreach ($this->levelOneComponents($wo, $fallback) as $line) {
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
            return $this->emptyReport($from, $to, $lineId, $workOrders->count(), $includeOpenOrders);
        }

        $onOrder = $includeOpenOrders ? $this->openOrderSupply($to) : [];

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
                $incoming = (float) ($onOrder[$materialId] ?? 0);

                // Stock (and incoming output) is claimed once, by whichever level
                // asks first — a material used both directly and inside a
                // subassembly must not spend the same units twice.
                $stockLeft[$materialId] ??= $onHand + $incoming;
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
                    'on_order_qty' => round($incoming, 4),
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
            'include_open_orders' => $includeOpenOrders,
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
     * The direct components of one order's product, per unit.
     *
     * The shop floor consumes the BOMs selected on the order (`work_order_boms`),
     * merged into its snapshot, so MRP reads the same selection: every selected
     * BOM, quantities summed per material. That is what
     * {@see \App\Services\WorkOrder\WorkOrderService::mergeBoms()} does too —
     * scrap is already folded into each line here, so a plain sum is exact.
     * An order with no selection falls back to the newest active template.
     *
     * @param  Collection<int, ProcessTemplate>  $fallback
     * @return list<array{material_id: int, required_per_unit: float}>
     */
    private function levelOneComponents(WorkOrder $wo, Collection $fallback): array
    {
        $templates = $wo->bomTemplates->isNotEmpty()
            ? $wo->bomTemplates
            : collect([$fallback->get($wo->product_type_id)])->filter();

        $perUnit = [];
        foreach ($templates as $template) {
            foreach ($this->directComponents($template) as $line) {
                $mid = $line['material_id'];
                $perUnit[$mid] = ($perUnit[$mid] ?? 0) + $line['required_per_unit'];
            }
        }

        return array_map(
            fn (int $mid, float $qty) => ['material_id' => $mid, 'required_per_unit' => $qty],
            array_keys($perUnit),
            array_values($perUnit),
        );
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

        // `per` and the packing step turn a per-carton or per-pallet line into a per-unit need.
        $lines = $template->bomItems()
            ->whereNotNull('material_id')
            ->with('templateStep:id,config')
            ->orderBy('sort_order')
            ->get(['id', 'material_id', 'quantity_per_unit', 'scrap_percentage', 'per', 'template_step_id'])
            ->map(fn ($item) => [
                'material_id' => (int) $item->material_id,
                'required_per_unit' => round(
                    $item->perUnitQuantity() * (1 + ((float) $item->scrap_percentage / 100)),
                    6
                ),
            ])
            ->all();

        return $this->componentCache[$templateId] = $lines;
    }

    /**
     * Output still to come from open work orders, per material: for each order
     * whose product type makes exactly one manufactured material, what is left of
     * it (the larger of planned and produced, less what it has already received
     * into stock through posted material receipts). Orders due after the period
     * cannot cover it and are left out; an order with no due date is counted.
     * Not filtered by line — stock is not either.
     *
     * @return array<int, float> material_id => quantity
     */
    private function openOrderSupply(Carbon $to): array
    {
        // product_type_id => material_id, only where the mapping is unambiguous.
        $materialByProduct = Material::query()
            ->where('is_manufactured', true)
            ->whereHas('producingTemplate')
            ->with('producingTemplate:id,product_type_id')
            ->get(['id', 'producing_process_template_id'])
            ->groupBy(fn (Material $m) => $m->producingTemplate->product_type_id)
            ->filter(fn (Collection $rows) => $rows->count() === 1)
            ->map(fn (Collection $rows) => (int) $rows->first()->id);

        if ($materialByProduct->isEmpty()) {
            return [];
        }

        $orders = WorkOrder::query()
            ->whereIn('status', self::SUPPLY_STATUSES)
            ->whereIn('product_type_id', $materialByProduct->keys())
            ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', $to))
            ->get(['id', 'product_type_id', 'planned_qty', 'produced_qty']);

        if ($orders->isEmpty()) {
            return [];
        }

        $received = StockDocumentLine::query()
            ->join('stock_documents', 'stock_documents.id', '=', 'stock_document_lines.stock_document_id')
            ->where('stock_documents.type', StockDocument::TYPE_MATERIAL_RECEIPT)
            ->where('stock_documents.status', StockDocument::STATUS_POSTED)
            ->whereNull('stock_documents.deleted_at')
            ->whereIn('stock_documents.work_order_id', $orders->pluck('id'))
            ->groupBy('stock_documents.work_order_id', 'stock_document_lines.material_id')
            ->selectRaw('stock_documents.work_order_id as work_order_id, stock_document_lines.material_id as material_id, SUM(stock_document_lines.quantity) as qty')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->work_order_id.':'.$r->material_id => (float) $r->qty]);

        $supply = [];
        foreach ($orders as $order) {
            $materialId = $materialByProduct->get($order->product_type_id);
            $expected = max((float) $order->planned_qty, (float) $order->produced_qty);
            $left = $expected - ($received[$order->id.':'.$materialId] ?? 0);
            if ($left > 0) {
                $supply[$materialId] = round(($supply[$materialId] ?? 0) + $left, 4);
            }
        }

        return $supply;
    }

    private function emptyReport(Carbon $from, Carbon $to, ?int $lineId, int $woCount, bool $includeOpenOrders): array
    {
        return [
            'period' => ['start' => $from->toDateString(), 'end' => $to->toDateString()],
            'line_id' => $lineId,
            'include_open_orders' => $includeOpenOrders,
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
