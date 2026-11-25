<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialLot;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * Read-only location map: zones and bins, and which material lots physically
 * sit in each bin. It deliberately does not move stock; the warehouse balance
 * remains the quantity source of truth.
 */
class WarehouseLocationController extends Controller
{
    private const LOT_STATUS_LABELS = [
        MaterialLot::STATUS_RECEIVED => '已收货',
        MaterialLot::STATUS_QUARANTINE => '待检 / 冻结',
        MaterialLot::STATUS_RELEASED => '已放行',
        MaterialLot::STATUS_CONSUMED => '已耗尽',
        MaterialLot::STATUS_EXPIRED => '已过期',
        MaterialLot::STATUS_REJECTED => '已拒收',
    ];

    public function index(Request $request)
    {
        $warehouseId = (int) $request->query('warehouse', 0);

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'kind']);

        $locations = WarehouseLocation::query()
            ->active()
            ->with(['warehouse:id,code,name,kind', 'parent:id,code,name'])
            ->when($warehouseId > 0, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('warehouse_id')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $lots = MaterialLot::query()
            ->whereIn('warehouse_location_id', $locations->pluck('id'))
            ->where(function ($query) {
                $query->where('quantity_available', '>', 0)
                    ->orWhereIn('status', [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED]);
            })
            ->with('material:id,code,name')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();

        $lotsByLocation = $lots->groupBy('warehouse_location_id');

        $productStocks = WarehouseStock::query()
            ->whereIn('warehouse_location_id', $locations->pluck('id'))
            ->whereNotNull('product_type_id')
            ->with('productType:id,code,name,unit_of_measure')
            ->orderBy('id')
            ->get();

        $productsByLocation = $productStocks->groupBy('warehouse_location_id');

        $rows = $locations->map(function (WarehouseLocation $location) use ($lotsByLocation, $productsByLocation) {
            $locationLots = $lotsByLocation->get($location->id, collect());
            $locationProducts = $productsByLocation->get($location->id, collect());
            $heldLots = $locationLots->whereIn('status', [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED]);

            $items = $locationLots->map(fn (MaterialLot $lot) => [
                'id' => 'lot-'.$lot->id,
                'kind' => 'material',
                'lot_number' => $lot->lot_number,
                'material_code' => $lot->material?->code ?? '—',
                'material_name' => $lot->material?->name ?? '—',
                'status' => $lot->status,
                'status_label' => self::LOT_STATUS_LABELS[$lot->status] ?? $lot->status,
                'available' => (float) $lot->quantity_available,
                'uom' => $lot->unit_of_measure,
                'received_at' => $lot->received_at?->format('Y-m-d'),
                'expiry' => $lot->expiry_date?->toDateString(),
                'url' => "/admin/material-lots/{$lot->id}",
            ])->concat($locationProducts->map(fn (WarehouseStock $stock) => [
                'id' => 'product-'.$stock->id,
                'kind' => 'product',
                'lot_number' => null,
                'material_code' => $stock->productType?->code ?? '—',
                'material_name' => $stock->productType?->name ?? '—',
                'status' => 'product',
                'status_label' => '成品',
                'available' => (float) $stock->quantity,
                'uom' => $stock->unit_of_measure,
                'received_at' => null,
                'expiry' => null,
                'url' => '/admin/warehouses/stock?type=product',
            ]));

            $availableByUom = $items
                ->groupBy('uom')
                ->map(fn (Collection $group) => round((float) $group->sum('available'), 3))
                ->all();

            $status = match (true) {
                $location->kind === WarehouseLocation::KIND_ZONE => 'ZONE',
                $heldLots->isNotEmpty() => 'HOLD',
                $items->isNotEmpty() => 'OCCUPIED',
                default => 'EMPTY',
            };

            return [
                'id' => $location->id,
                'warehouse_id' => $location->warehouse_id,
                'warehouse_code' => $location->warehouse?->code ?? '—',
                'warehouse_name' => $location->warehouse?->name ?? '—',
                'warehouse_kind' => $location->warehouse?->kind,
                'parent_id' => $location->parent_id,
                'parent_code' => $location->parent?->code,
                'parent_name' => $location->parent?->name,
                'code' => $location->code,
                'name' => $location->name,
                'kind' => $location->kind,
                'kind_label' => $location->kind === WarehouseLocation::KIND_ZONE ? '库区' : '库位',
                'description' => $location->description,
                'sort_order' => $location->sort_order,
                'status' => $status,
                'status_label' => match ($status) {
                    'HOLD' => '冻结 / 待处理',
                    'OCCUPIED' => '有料',
                    'EMPTY' => '空库位',
                    default => '库区',
                },
                'lots_count' => $items->count(),
                'material_lots_count' => $locationLots->count(),
                'products_count' => $locationProducts->count(),
                'held_lots' => $heldLots->count(),
                'available_by_uom' => $availableByUom,
                'last_received' => $locationLots->max('received_at')?->format('Y-m-d'),
                'lots' => $items->values(),
            ];
        });

        $bins = $rows->where('kind', WarehouseLocation::KIND_BIN);
        $unassignedLots = MaterialLot::query()
            ->whereNull('warehouse_location_id')
            ->whereIn('status', [
                MaterialLot::STATUS_RECEIVED,
                MaterialLot::STATUS_QUARANTINE,
                MaterialLot::STATUS_RELEASED,
                MaterialLot::STATUS_REJECTED,
            ])
            ->count();
        $unassignedProducts = WarehouseStock::query()
            ->whereNull('warehouse_location_id')
            ->whereNotNull('product_type_id')
            ->count();

        $summary = [
            'warehouses' => $warehouses->count(),
            'zones' => $rows->where('kind', WarehouseLocation::KIND_ZONE)->count(),
            'bins' => $bins->count(),
            'occupied_bins' => $bins->whereIn('status', ['OCCUPIED', 'HOLD'])->count(),
            'hold_bins' => $bins->where('status', 'HOLD')->count(),
            'located_lots' => $bins->sum('material_lots_count'),
            'located_products' => $bins->sum('products_count'),
            'located_items' => $bins->sum('lots_count'),
            'unassigned_items' => $unassignedLots + $unassignedProducts,
        ];

        return Inertia::render('admin/warehouses/Locations', [
            'locations' => $rows->values(),
            'summary' => $summary,
            'warehouses' => $warehouses,
            'filters' => [
                'warehouse' => $warehouseId ?: '',
            ],
        ]);
    }
}
