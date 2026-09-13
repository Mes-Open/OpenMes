<?php

namespace App\Services\WorkOrder;

use App\Models\ComponentStockReservation;
use App\Models\MaterialLot;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use App\Models\WorkOrderComponent;
use App\Services\Warehouse\StockDocumentService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Stock allocation for manufactured BOM occurrences. Purchased material planning
 * remains in the existing material flow. A preview is read-only; net(lock: true),
 * persist/reserve and issue must share the caller's transaction.
 */
class ComponentStockService
{
    public function net(array $plan, array $options, bool $lock = false): array
    {
        $useStock = filter_var($options['use_component_stock'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $warehouses = array_values(array_unique(array_map('intval', $options['component_warehouse_ids'] ?? [])));
        if ($useStock && (! $warehouses || Warehouse::whereIn('id', $warehouses)->where('is_active', true)->count() !== count($warehouses))) {
            throw ValidationException::withMessages(['component_warehouse_ids' => __('Select active warehouses for component stock.')]);
        }
        $flatten = function (array $nodes) use (&$flatten): array {
            return array_merge($nodes, ...array_map(fn ($node) => $flatten($node['children']), $nodes));
        };
        $manufactured = collect($flatten($plan['components']))->filter(fn ($node) => $node['snapshot'] !== null);
        $stocks = $useStock ? WarehouseStock::whereIn('warehouse_id', $warehouses)
            ->where(fn ($q) => $q->whereIn('product_type_id', $manufactured->pluck('specification.product_type_id')->filter())
                ->orWhereIn('material_id', $manufactured->pluck('specification.material_id')->filter()))
            ->with(['warehouse', 'materialLot'])->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get() : collect();
        // Read reservations after acquiring the balance locks, including a competing creator's commit.
        $held = ComponentStockReservation::whereIn('warehouse_stock_id', $stocks->pluck('id'))->where('status', 'held')
            ->groupBy('warehouse_stock_id')->selectRaw('warehouse_stock_id, SUM(quantity) as total')->pluck('total', 'warehouse_stock_id');
        $pool = $stocks->mapWithKeys(fn ($stock) => [$stock->id => max(0, (float) $stock->quantity - (float) ($held[$stock->id] ?? 0))])->all();
        $lotIds = $stocks->pluck('material_lot_id')->filter()->unique();
        $lots = MaterialLot::whereIn('id', $lotIds)->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');
        $lotHeld = ComponentStockReservation::join('warehouse_stocks', 'warehouse_stocks.id', '=', 'component_stock_reservations.warehouse_stock_id')
            ->whereIn('warehouse_stocks.material_lot_id', $lotIds)->where('component_stock_reservations.status', 'held')
            ->groupBy('warehouse_stocks.material_lot_id')->selectRaw('warehouse_stocks.material_lot_id, SUM(component_stock_reservations.quantity) as total')->pluck('total', 'material_lot_id');
        $lotPool = $lots->mapWithKeys(fn ($lot) => [$lot->id => max(0, (float) $lot->quantity_available - (float) ($lotHeld[$lot->id] ?? 0))])->all();
        foreach ($stocks as $stock) {
            if ($stock->material_lot_id) {
                $stock->setRelation('materialLot', $lots[$stock->material_lot_id] ?? null);
            }
        }
        $excluded = $options['excluded_component_paths'] ?? [];
        $seen = [];
        $walk = function ($nodes, $parentQty, $neededAt = null) use (&$walk, &$pool, &$lotPool, &$seen, $stocks, $excluded) {
            foreach ($nodes as &$node) {
                $seen[] = $node['path'];
                $manufactured = $node['snapshot'] !== null;
                [$required, $planned] = $parentQty > 0
                    ? app(ComponentPlanService::class)->quantities($node['specification'], $parentQty, $node['path'], $manufactured)
                    : [0, 0];
                $node['required_qty'] = $required;
                $node['gross_planned_qty'] = $planned;
                $node['stock_qty'] = 0;
                $node['available_qty'] = 0;
                $node['stock_picks'] = [];
                if ($manufactured) {
                    $displayLotPool = $lotPool;
                    foreach ($stocks as $stock) {
                        if (! $this->eligible($stock, $node['specification'], $neededAt)) {
                            continue;
                        }
                        $available = $stock->material_lot_id ? min($pool[$stock->id], $lotPool[$stock->material_lot_id] ?? 0) : $pool[$stock->id];
                        $displayAvailable = $stock->material_lot_id ? min($pool[$stock->id], $displayLotPool[$stock->material_lot_id] ?? 0) : $pool[$stock->id];
                        $node['available_qty'] += $displayAvailable;
                        if ($stock->material_lot_id) {
                            $displayLotPool[$stock->material_lot_id] -= $displayAvailable;
                        }
                        // Stock is decimal:3; never promise a fraction we cannot book.
                        $take = floor(min($available, max(0, $required - $node['stock_qty'])) * 1000 + 0.00001) / 1000;
                        if ($take <= 0) {
                            continue;
                        }
                        $pool[$stock->id] -= $take;
                        if ($stock->material_lot_id) {
                            $lotPool[$stock->material_lot_id] -= $take;
                        }
                        $node['stock_qty'] += $take;
                        $node['stock_picks'][] = ['warehouse_stock_id' => $stock->id, 'quantity' => $take];
                    }
                    $missing = max(0, round($required - $node['stock_qty'], 4));
                    if ($missing > 0 && $node['stock_qty'] > 0) {
                        [, $planned] = app(ComponentPlanService::class)->quantities(array_merge($node['specification'], ['quantity_per_unit' => 1]), $missing, $node['path'], true);
                    } elseif ($missing <= 0) {
                        $planned = 0;
                    }
                    if (in_array($node['path'], $excluded, true) && $missing > 0) {
                        throw ValidationException::withMessages(['excluded_component_paths' => __('Component :code still requires production.', ['code' => $node['specification']['material_code']])]);
                    }
                }
                $node['planned_qty'] = $planned;
                $node['children'] = $walk($node['children'], $manufactured ? $planned : 0);
            }

            return $nodes;
        };
        $plan['components'] = $walk($plan['components'], $plan['quantity'], $options['planned_start_at'] ?? null);
        if (array_diff($excluded, $seen)) {
            throw ValidationException::withMessages(['excluded_component_paths' => __('The component selection is outdated. Refresh the preview.')]);
        }
        $plan['stock_options'] = ['use_component_stock' => $useStock, 'component_warehouse_ids' => $warehouses, 'excluded_component_paths' => $excluded];
        $plan['preview_token'] = hash('sha256', json_encode([$plan['components'], $useStock, $warehouses]));

        return $plan;
    }

    public function eligible(WarehouseStock $stock, array $spec, $neededAt = null): bool
    {
        if ($stock->component_status !== 'released' || ! $stock->warehouse?->is_active) {
            return false;
        }
        if (! empty($spec['product_type_id'])) {
            if ((int) $stock->product_type_id !== (int) $spec['product_type_id']) {
                return false;
            }
        } elseif (! empty($spec['material_id'])) {
            if ((int) $stock->material_id !== (int) $spec['material_id']) {
                return false;
            }
            // Aggregate rows duplicate lot balances; use eligible lots when they exist.
            if (! $stock->material_lot_id && WarehouseStock::where('warehouse_id', $stock->warehouse_id)->where('material_id', $stock->material_id)->whereNotNull('material_lot_id')->exists()) {
                return false;
            }
            if ($stock->material_lot_id) {
                $lot = $stock->materialLot;
                if (! $lot || $lot->status !== MaterialLot::STATUS_RELEASED || ($lot->expiry_date && $lot->expiry_date->endOfDay()->lt($neededAt ? max(now(), Carbon::parse($neededAt)) : now()))) {
                    return false;
                }
            }
        } else {
            return false;
        }
        if ($stock->unit_of_measure && ($spec['unit_of_measure'] ?? null) !== $stock->unit_of_measure) {
            return false;
        }
        $identity = $stock->component_specification ?? [];
        foreach (['component_template_id', 'product_revision_id'] as $key) {
            if (! empty($spec[$key]) && ($identity[$key] ?? null) != $spec[$key]) {
                return false;
            }
        }
        foreach ($spec['extra_data'] ?? [] as $key => $value) {
            if (($identity['extra_data'][$key] ?? null) != $value) {
                return false;
            }
        }

        return true;
    }

    public function reserve(WorkOrderComponent $component, array $node, WorkOrder $parent): void
    {
        foreach ($node['stock_picks'] ?? [] as $pick) {
            ComponentStockReservation::create($pick + ['work_order_component_id' => $component->id, 'needed_at' => $parent->planned_start_at]);
        }
    }

    public function covered(WorkOrderComponent $component): float
    {
        $quantity = 0;
        foreach ($component->reservations()->with('stock.warehouse', 'stock.materialLot')->get() as $reservation) {
            if ($reservation->status === 'issued') {
                $quantity += (float) $reservation->quantity;
            } elseif ($reservation->status === 'held' && $reservation->stock && $this->eligible($reservation->stock, $component->specification, $reservation->needed_at)) {
                $held = ComponentStockReservation::where('warehouse_stock_id', $reservation->warehouse_stock_id)->where('status', 'held')->sum('quantity');
                $lot = $reservation->stock->materialLot;
                $lotHeld = $lot ? ComponentStockReservation::whereIn('warehouse_stock_id', WarehouseStock::where('material_lot_id', $lot->id)->pluck('id'))->where('status', 'held')->sum('quantity') : 0;
                if ((float) $reservation->stock->quantity >= (float) $held && (! $lot || (float) $lot->quantity_available >= (float) $lotHeld)) {
                    $quantity += (float) $reservation->quantity;
                }
            }
        }

        return $quantity;
    }

    public function issue(WorkOrder $order, int $stepNumber, $user): void
    {
        $ids = $order->components()->where('consuming_step_number', '<=', $stepNumber)->pluck('id');
        $reservations = ComponentStockReservation::whereIn('work_order_component_id', $ids)->where('status', 'held')->orderBy('warehouse_stock_id')->lockForUpdate()->get();
        foreach ($reservations as $reservation) {
            $stock = WarehouseStock::whereKey($reservation->warehouse_stock_id)->lockForUpdate()->firstOrFail();
            if (! $this->eligible($stock, $reservation->component->specification, $reservation->needed_at) || $this->covered($reservation->component) < (float) $reservation->component->stock_qty) {
                throw ValidationException::withMessages(['components' => __('Reserved component stock is no longer available.')]);
            }
            // Own reservation is converted atomically into a posted issue. Other reservations stay protected.
            $reservation->update(['status' => 'issued']);
            $documents = app(StockDocumentService::class);
            $document = $documents->createDraft([
                'document_no' => 'COMP-ISSUE-'.$reservation->id,
                'type' => $stock->product_type_id ? 'product_issue' : 'material_issue',
                'warehouse_id' => $stock->warehouse_id,
                'work_order_id' => $order->id,
                'lines' => [['product_type_id' => $stock->product_type_id, 'material_id' => $stock->material_id,
                    'material_lot_id' => $stock->material_lot_id, 'quantity' => $reservation->quantity, 'unit_of_measure' => $stock->unit_of_measure]],
            ], $user);
            $documents->post($document, $user);
            $reservation->update(['stock_document_id' => $document->id]);
        }
    }

    public function release(WorkOrder $order, bool $family = false): void
    {
        $components = $family ? WorkOrderComponent::where('root_work_order_id', $order->id) : $order->components();
        ComponentStockReservation::whereIn('work_order_component_id', $components->pluck('id'))->where('status', 'held')->update(['status' => 'released']);
    }

    public function syncDates(WorkOrder $order): void
    {
        ComponentStockReservation::whereIn('work_order_component_id', $order->components()->pluck('id'))->where('status', 'held')->update(['needed_at' => $order->planned_start_at]);
    }
}
