<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialLot;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseLocationMove;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Location map: zones and bins, the lots/products in each bin, and an audited
 * whole-item transfer. The transfer only repoints the physical location; the
 * warehouse balance remains the quantity source of truth.
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
                'item_id' => $lot->id,
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
                'item_id' => $stock->id,
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

        $moves = WarehouseLocationMove::query()
            ->with(['fromLocation:id,code,name', 'toLocation:id,code,name', 'movedBy:id,name'])
            ->when($warehouseId > 0, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->latest('moved_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (WarehouseLocationMove $move) => [
                'id' => $move->id,
                'item_type' => $move->item_type,
                'item_type_label' => $move->item_type === WarehouseLocationMove::ITEM_MATERIAL_LOT ? '物料批' : '成品',
                'item_code' => $move->item_code,
                'item_name' => $move->item_name,
                'quantity' => (float) $move->quantity,
                'uom' => $move->unit_of_measure,
                'from_code' => $move->fromLocation?->code,
                'from_name' => $move->fromLocation?->name,
                'to_code' => $move->toLocation?->code,
                'to_name' => $move->toLocation?->name,
                'moved_by' => $move->movedBy?->name ?? '系统',
                'moved_at' => $move->moved_at?->format('Y-m-d H:i'),
                'note' => $move->note,
            ]);

        return Inertia::render('admin/warehouses/Locations', [
            'locations' => $rows->values(),
            'moves' => $moves,
            'summary' => $summary,
            'warehouses' => $warehouses,
            'filters' => [
                'warehouse' => $warehouseId ?: '',
            ],
        ]);
    }

    public function move(Request $request)
    {
        $data = $request->validate([
            'item_type' => 'required|in:material_lot,product_stock',
            'item_id' => 'required|integer|min:1',
            'to_location_id' => 'required|integer|exists:warehouse_locations,id',
            'note' => 'nullable|string|max:160',
        ]);

        $target = WarehouseLocation::query()
            ->active()
            ->whereKey($data['to_location_id'])
            ->first();

        if (! $target || $target->kind !== WarehouseLocation::KIND_BIN) {
            throw ValidationException::withMessages([
                'to_location_id' => '目标必须是启用中的库位，不能直接移到库区。',
            ]);
        }

        $warehouseId = $target->warehouse_id;
        $movedBy = $request->user()?->id;
        $note = trim((string) ($data['note'] ?? ''));

        DB::transaction(function () use ($data, $target, $warehouseId, $movedBy, $note) {
            $source = null;
            $snapshot = [];

            if ($data['item_type'] === WarehouseLocationMove::ITEM_MATERIAL_LOT) {
                $lot = MaterialLot::query()->whereKey($data['item_id'])->lockForUpdate()->first();

                if (! $lot || $lot->warehouse_id !== $warehouseId) {
                    throw ValidationException::withMessages([
                        'item_id' => '这个物料批不属于目标仓库。',
                    ]);
                }

                $source = $lot->warehouse_location_id;
                $snapshot = [
                    'item_type' => WarehouseLocationMove::ITEM_MATERIAL_LOT,
                    'item_id' => $lot->id,
                    'item_code' => trim(($lot->material?->code ?? '').' · '.$lot->lot_number, ' ·'),
                    'item_name' => $lot->material?->name ?? $lot->lot_number,
                    'quantity' => (float) $lot->quantity_available,
                    'unit_of_measure' => $lot->unit_of_measure,
                ];
            } else {
                $stock = WarehouseStock::query()->whereKey($data['item_id'])->lockForUpdate()->first();

                if (! $stock || $stock->warehouse_id !== $warehouseId || $stock->product_type_id === null) {
                    throw ValidationException::withMessages([
                        'item_id' => '这个成品库存不属于目标仓库。',
                    ]);
                }

                $source = $stock->warehouse_location_id;
                $snapshot = [
                    'item_type' => WarehouseLocationMove::ITEM_PRODUCT_STOCK,
                    'item_id' => $stock->id,
                    'item_code' => $stock->productType?->code,
                    'item_name' => $stock->productType?->name ?? '成品',
                    'quantity' => (float) $stock->quantity,
                    'unit_of_measure' => $stock->unit_of_measure,
                ];
            }

            if ($source !== null && (int) $source === (int) $target->id) {
                throw ValidationException::withMessages([
                    'to_location_id' => '目标库位与当前库位相同。',
                ]);
            }

            if ($data['item_type'] === WarehouseLocationMove::ITEM_MATERIAL_LOT) {
                MaterialLot::whereKey($data['item_id'])->update(['warehouse_location_id' => $target->id]);
            } else {
                WarehouseStock::whereKey($data['item_id'])->update(['warehouse_location_id' => $target->id]);
            }

            WarehouseLocationMove::create([
                'warehouse_id' => $warehouseId,
                'from_location_id' => $source,
                'to_location_id' => $target->id,
                ...$snapshot,
                'moved_by' => $movedBy,
                'moved_at' => now(),
                'note' => $note !== '' ? $note : null,
            ]);
        });

        return back()->with('success', '库位已调整，库存数量未变。');
    }
}
