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

        // BOM per product type, exploded through every subassembly level to the
        // materials that actually have to be bought or drawn from stock.
        $bomByProductType = $this->bomByProductType($workOrders->pluck('product_type_id')->unique()->filter());

        // Accumulate gross requirement + the driving work orders, per material.
        $gross = [];          // material_id => qty
        $relatedWos = [];     // material_id => [order_no => true]
        foreach ($workOrders as $wo) {
            $lines = $bomByProductType->get($wo->product_type_id, collect());
            foreach ($lines as $line) {
                // required_per_unit already carries the scrap compounded through
                // every level of the explosion, so it only needs scaling here.
                $required = round((float) $line['required_per_unit'] * (float) $wo->planned_qty, 4);
                if ($required <= 0) {
                    continue;
                }
                $mid = $line['material_id'];
                $gross[$mid] = ($gross[$mid] ?? 0) + $required;
                $relatedWos[$mid][$wo->order_no] = true;
            }
        }

        if (empty($gross)) {
            return $this->emptyReport($from, $to, $lineId, $workOrders->count());
        }

        $materials = Material::whereIn('id', array_keys($gross))->get()->keyBy('id');

        $requirements = [];
        foreach ($gross as $materialId => $grossQty) {
            $material = $materials->get($materialId);
            $onHand = (float) ($material?->stock_quantity ?? 0);
            $net = round(max(0, $grossQty - $onHand), 4);

            $requirements[] = [
                'material_id' => $materialId,
                'code' => $material?->code,
                'name' => $material?->name ?? __('Unknown'),
                'unit_of_measure' => $material?->unit_of_measure,
                'required_qty' => round($grossQty, 4),
                'available_qty' => round($onHand, 4),
                'net_qty' => $net,
                'is_short' => $net > 0,
                'related_work_orders' => array_keys($relatedWos[$materialId] ?? []),
            ];
        }

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
     * Build a map of product_type_id => collection of leaf requirement lines
     * (material_id, required_per_unit) from each type's active template,
     * exploded through every subassembly level.
     *
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    private function bomByProductType(Collection $productTypeIds): Collection
    {
        if ($productTypeIds->isEmpty()) {
            return collect();
        }

        // Active template id per product type (highest version, active).
        $templateIds = ProcessTemplate::whereIn('product_type_id', $productTypeIds)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->get(['id', 'product_type_id'])
            ->groupBy('product_type_id')
            ->map(fn ($rows) => $rows->first()->id);

        if ($templateIds->isEmpty()) {
            return collect();
        }

        // Explode each template for a single unit: leaf quantities come back
        // with scrap already compounded through every level, so the caller only
        // scales by planned_qty. Subassemblies are resolved into what they are
        // made of rather than counted as demand themselves — netting a
        // manufactured item against its own stock is a separate MRP concern.
        $templates = ProcessTemplate::whereIn('id', $templateIds->values())->get()->keyBy('id');
        $productTypeByTemplate = $templateIds->flip();

        return $templateIds->values()
            ->mapWithKeys(function (int $templateId) use ($templates, $productTypeByTemplate) {
                $template = $templates->get($templateId);

                $lines = $template
                    ? collect($this->explosion->leafRequirements($template, 1.0))
                        ->map(fn (array $leaf) => [
                            'material_id' => $leaf['material_id'],
                            'required_per_unit' => (float) $leaf['required_qty'],
                        ])
                    : collect();

                return [$productTypeByTemplate->get($templateId) => $lines];
            })
            ->filter(fn ($lines) => $lines->isNotEmpty());
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
