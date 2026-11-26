<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialLot;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseLocationMove;
use App\Models\WarehouseStock;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
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
            ->with(['warehouse:id,code,name,kind', 'parent:id,code,name,sort_order'])
            ->when($warehouseId > 0, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('warehouse_id')
            ->get()
            ->sortBy(fn (WarehouseLocation $location) => sprintf('%05d-%05d-%05d-%s', $location->warehouse_id, $location->parent?->sort_order ?? 0, $location->sort_order, $location->code))
            ->values();

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

        // Row-level material stock has no material lot to point at a bin. The
        // reconciliation page can still assign it a location, so the location
        // map must show those rows too; otherwise B-01/B-02 look empty while
        // the stock ledger reports 36,000 cups and 12,500 labels there.
        $materialStocks = WarehouseStock::query()
            ->whereIn('warehouse_location_id', $locations->pluck('id'))
            ->whereNull('product_type_id')
            ->where('quantity', '>', 0)
            ->with('material:id,code,name,unit_of_measure,tracking_type')
            ->orderBy('id')
            ->get();

        $materialStocksByLocation = $materialStocks->groupBy('warehouse_location_id');

        $rows = $locations->map(function (WarehouseLocation $location) use ($lotsByLocation, $productsByLocation, $materialStocksByLocation) {
            $locationLots = $lotsByLocation->get($location->id, collect());
            $locationProducts = $productsByLocation->get($location->id, collect());
            $locationMaterialStocks = $materialStocksByLocation->get($location->id, collect())
                ->reject(fn (WarehouseStock $stock) => $locationLots->contains(
                    fn (MaterialLot $lot) => (int) $lot->material_id === (int) $stock->material_id,
                ));
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
            ])->concat($locationMaterialStocks->map(fn (WarehouseStock $stock) => [
                'id' => 'stock-'.$stock->id,
                'item_id' => $stock->id,
                'kind' => 'stock',
                'lot_number' => null,
                'material_code' => $stock->material?->code ?? '—',
                'material_name' => $stock->material?->name ?? '—',
                'status' => 'stock',
                'status_label' => $stock->material?->tracking_type === 'batch' ? '缺批次' : '汇总库存',
                'available' => (float) $stock->quantity,
                'uom' => $stock->unit_of_measure,
                'received_at' => null,
                'expiry' => null,
                'url' => '/admin/warehouses/stock',
            ]))->concat($locationProducts->map(fn (WarehouseStock $stock) => [
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
                'material_stocks_count' => $locationMaterialStocks->count(),
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
            'located_stocks' => $bins->sum('material_stocks_count'),
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
            'item_type' => 'required|in:material_lot,material_stock,product_stock',
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
            } elseif ($data['item_type'] === WarehouseLocationMove::ITEM_MATERIAL_STOCK) {
                $stock = WarehouseStock::query()->whereKey($data['item_id'])->lockForUpdate()->first();

                if (! $stock || $stock->warehouse_id !== $warehouseId || $stock->product_type_id !== null || $stock->material_id === null) {
                    throw ValidationException::withMessages([
                        'item_id' => '这个物料库存不属于目标仓库。',
                    ]);
                }

                if (MaterialLot::query()->where('warehouse_id', $warehouseId)->where('material_id', $stock->material_id)->exists()) {
                    throw ValidationException::withMessages([
                        'item_id' => '这个物料已有批次库位，请在库位地图按批次调整。',
                    ]);
                }

                $source = $stock->warehouse_location_id;
                $snapshot = [
                    'item_type' => WarehouseLocationMove::ITEM_MATERIAL_STOCK,
                    'item_id' => $stock->id,
                    'item_code' => $stock->material?->code,
                    'item_name' => $stock->material?->name ?? '物料',
                    'quantity' => (float) $stock->quantity,
                    'unit_of_measure' => $stock->unit_of_measure,
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

        return back()->with('success', $data['item_type'] === WarehouseLocationMove::ITEM_MATERIAL_STOCK
            ? '库位已指定，库存数量未变。'
            : '库位已调整，库存数量未变。');
    }

    public function moves(Request $request)
    {
        [$query, $filters] = $this->moveQuery($request);

        $summary = [
            'total' => (clone $query)->count(),
            'material_lot' => (clone $query)->where('item_type', WarehouseLocationMove::ITEM_MATERIAL_LOT)->count(),
            'material_stock' => (clone $query)->where('item_type', WarehouseLocationMove::ITEM_MATERIAL_STOCK)->count(),
            'product_stock' => (clone $query)->where('item_type', WarehouseLocationMove::ITEM_PRODUCT_STOCK)->count(),
            'operators' => (clone $query)->whereNotNull('moved_by')->distinct('moved_by')->count('moved_by'),
            'last_moved_at' => (clone $query)->max('moved_at'),
        ];

        $moves = $query
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/warehouses/LocationMoves', [
            'moves' => $moves,
            'summary' => $summary,
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => $filters,
        ]);
    }

    public function exportMoves(Request $request)
    {
        [$query, $filters] = $this->moveQuery($request);
        $moves = $query->orderByDesc('moved_at')->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($moves) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['移库时间', '仓库', '类型', '对象编码', '对象名称', '数量', '单位', '原库位', '目标库位', '操作人', '备注']);

            foreach ($moves as $move) {
                fputcsv($out, [
                    $move->moved_at?->format('Y-m-d H:i:s'),
                    $move->warehouse?->name,
                    match ($move->item_type) {
                        WarehouseLocationMove::ITEM_MATERIAL_LOT => '物料批',
                        WarehouseLocationMove::ITEM_MATERIAL_STOCK => '物料库存',
                        default => '成品库存',
                    },
                    $move->item_code,
                    $move->item_name,
                    $move->quantity,
                    $move->unit_of_measure,
                    $move->fromLocation?->code,
                    $move->toLocation?->code,
                    $move->movedBy?->name,
                    $move->note,
                ]);
            }

            fclose($out);
        }, 'location-moves-'.now()->format('Ymd-Hi').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function moveQuery(Request $request): array
    {
        $filters = [
            'warehouse' => (int) $request->query('warehouse', 0) ?: '',
            'type' => (string) $request->query('type', ''),
            'q' => trim((string) $request->query('q', '')),
        ];

        $query = WarehouseLocationMove::query()
            ->with([
                'warehouse:id,code,name',
                'fromLocation:id,code,name',
                'toLocation:id,code,name',
                'movedBy:id,name,username',
            ])
            ->when($filters['warehouse'], fn ($query) => $query->where('warehouse_id', $filters['warehouse']))
            ->when(in_array($filters['type'], WarehouseLocationMove::ITEM_TYPES, true), fn ($query) => $query->where('item_type', $filters['type']))
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $query->where(function ($query) use ($term) {
                    $query->where('item_code', 'like', $term)
                        ->orWhere('item_name', 'like', $term)
                        ->orWhere('note', 'like', $term)
                        ->orWhereHas('movedBy', fn ($query) => $query->where('name', 'like', $term)->orWhere('username', 'like', $term));
                });
            });

        return [$query, $filters];
    }

    public function reconcile(Request $request)
    {
        $warehouseId = (int) $request->query('warehouse', 0);

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->when($warehouseId > 0, fn ($query) => $query->where('id', $warehouseId))
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'kind']);

        $warehouseIds = $warehouses->pluck('id');

        $locations = WarehouseLocation::query()
            ->active()
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('kind', WarehouseLocation::KIND_BIN)
            ->with(['parent:id,code,name,sort_order'])
            ->orderBy('warehouse_id')
            ->get()
            ->sortBy(fn (WarehouseLocation $location) => sprintf('%05d-%05d-%05d-%s', $location->warehouse_id, $location->parent?->sort_order ?? 0, $location->sort_order, $location->code))
            ->values();

        $lots = MaterialLot::query()
            ->whereIn('warehouse_location_id', $locations->pluck('id'))
            ->with('material:id,code,name')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();

        $products = WarehouseStock::query()
            ->whereIn('warehouse_location_id', $locations->pluck('id'))
            ->whereNotNull('product_type_id')
            ->with('productType:id,code,name,unit_of_measure')
            ->orderBy('id')
            ->get();

        $assignedMaterialStocks = WarehouseStock::query()
            ->whereIn('warehouse_location_id', $locations->pluck('id'))
            ->whereNull('product_type_id')
            ->whereNotNull('material_id')
            ->with('material:id,code,name')
            ->orderBy('id')
            ->get();

        $stocks = WarehouseStock::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->with([
                'material:id,code,name',
                'productType:id,code,name,unit_of_measure',
                'location:id,code,name',
            ])
            ->orderBy('warehouse_id')
            ->orderBy('id')
            ->get();

        $lotsByLocation = $lots->groupBy('warehouse_location_id');
        $productsByLocation = $products->groupBy('warehouse_location_id');
        $materialStocksByLocation = $assignedMaterialStocks->groupBy('warehouse_location_id');
        $stocksByWarehouse = $stocks->groupBy('warehouse_id');
        $locationsByWarehouse = $locations->groupBy('warehouse_id');

        $locatedMaterialQuantity = $lots
            ->groupBy(fn (MaterialLot $lot) => $lot->warehouse_id.'|'.$lot->material_id)
            ->map(fn (Collection $group) => round((float) $group->sum('quantity_available'), 3));

        $rows = $warehouses->map(function (Warehouse $warehouse) use (
            $locationsByWarehouse,
            $lotsByLocation,
            $productsByLocation,
            $materialStocksByLocation,
            $stocksByWarehouse,
            $locatedMaterialQuantity,
            $lots,
            $locations
        ) {
            $warehouseLocations = $locationsByWarehouse->get($warehouse->id, collect())->map(function (WarehouseLocation $location) use ($lotsByLocation, $productsByLocation, $materialStocksByLocation) {
                $locationLots = $lotsByLocation->get($location->id, collect());
                $locationMaterialStocks = $materialStocksByLocation->get($location->id, collect());
                $locationProducts = $productsByLocation->get($location->id, collect());

                $items = $locationLots->map(fn (MaterialLot $lot) => [
                    'key' => 'lot-'.$lot->id,
                    'kind' => 'material',
                    'code' => $lot->material?->code ?? '—',
                    'name' => $lot->material?->name ?? '—',
                    'lot_number' => $lot->lot_number,
                    'quantity' => (float) $lot->quantity_available,
                    'uom' => $lot->unit_of_measure,
                    'status' => $lot->status,
                    'status_label' => self::LOT_STATUS_LABELS[$lot->status] ?? $lot->status,
                    'is_held' => in_array($lot->status, [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED], true),
                ])->concat($locationMaterialStocks->map(fn (WarehouseStock $stock) => [
                    'key' => 'material-stock-'.$stock->id,
                    'kind' => 'stock',
                    'code' => $stock->material?->code ?? '—',
                    'name' => $stock->material?->name ?? '—',
                    'lot_number' => null,
                    'quantity' => (float) $stock->quantity,
                    'uom' => $stock->unit_of_measure,
                    'status' => 'stock',
                    'status_label' => '账面库存',
                    'is_held' => false,
                ]))->concat($locationProducts->map(fn (WarehouseStock $stock) => [
                    'key' => 'product-'.$stock->id,
                    'kind' => 'product',
                    'code' => $stock->productType?->code ?? '—',
                    'name' => $stock->productType?->name ?? '—',
                    'lot_number' => null,
                    'quantity' => (float) $stock->quantity,
                    'uom' => $stock->unit_of_measure,
                    'status' => 'product',
                    'status_label' => '成品',
                    'is_held' => false,
                ]));

                $items = $items->sortBy(fn (array $item) => sprintf('%d-%s-%s', $item['kind'] === 'material' ? 0 : 1, $item['code'], $item['lot_number'] ?? ''))->values();

                $units = $items
                    ->groupBy('uom')
                    ->map(fn (Collection $group) => round((float) $group->sum('quantity'), 3))
                    ->all();

                return [
                    'id' => $location->id,
                    'code' => $location->code,
                    'name' => $location->name,
                    'zone_code' => $location->parent?->code ?? '—',
                    'zone_name' => $location->parent?->name ?? '—',
                    'items' => $items->values()->all(),
                    'item_count' => $items->count(),
                    'units' => $units,
                    'held_count' => $items->where('is_held', true)->count(),
                ];
            })->values();

            $stockLines = $stocksByWarehouse->get($warehouse->id, collect())->map(function (WarehouseStock $stock) use ($locatedMaterialQuantity, $lots, $locations) {
                $balance = round((float) $stock->quantity, 3);
                $isProduct = $stock->product_type_id !== null;

                if ($isProduct) {
                    $located = $stock->warehouse_location_id ? $balance : 0.0;
                    $locationCodes = $stock->location?->code ? [$stock->location->code] : [];
                    $relatedLots = collect();
                } else {
                    $located = (float) ($locatedMaterialQuantity->get($stock->warehouse_id.'|'.$stock->material_id, 0));
                    $relatedLots = $lots
                        ->where('warehouse_id', $stock->warehouse_id)
                        ->where('material_id', $stock->material_id);

                    if ($relatedLots->isEmpty() && $stock->warehouse_location_id) {
                        $located = $balance;
                        $locationCodes = [$stock->location?->code];
                    } else {
                        $locationCodes = $relatedLots
                            ->pluck('warehouse_location_id')
                            ->filter()
                            ->unique()
                            ->map(fn ($id) => $locations->firstWhere('id', $id)?->code)
                            ->filter()
                            ->values()
                            ->all();
                    }
                }

                $variance = round($located - $balance, 3);
                $unlocated = max(round($balance - $located, 3), 0);

                $status = match (true) {
                    $located <= 0 && $balance > 0 => 'UNLOCATED',
                    $variance < 0 => 'PARTIAL',
                    $variance > 0 => 'OVER',
                    default => 'OK',
                };

                return [
                    'id' => $stock->id,
                    'kind' => $isProduct ? 'product' : 'material',
                    'code' => $isProduct ? ($stock->productType?->code ?? '—') : ($stock->material?->code ?? '—'),
                    'name' => $isProduct ? ($stock->productType?->name ?? '—') : ($stock->material?->name ?? '—'),
                    'balance' => $balance,
                    'located' => round($located, 3),
                    'variance' => $variance,
                    'unlocated' => $unlocated,
                    'uom' => $stock->unit_of_measure,
                    'status' => $status,
                    'location_codes' => $locationCodes,
                    'lot_count' => $relatedLots->count(),
                    'held_lot_count' => $relatedLots->whereIn('status', [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED])->count(),
                ];
            })->values();

            $unassigned = $stockLines
                ->where('unlocated', '>', 0)
                ->sortBy('name')
                ->values();

            return [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'locations' => $warehouseLocations,
                'stock_lines' => $stockLines,
                'unassigned' => $unassigned,
                'stock_count' => $stockLines->count(),
                'located_count' => $stockLines->where('status', 'OK')->count(),
                'unlocated_count' => $unassigned->count(),
                'variance_count' => $stockLines->whereIn('status', ['PARTIAL', 'OVER'])->count(),
            ];
        })->values();

        $allStockLines = $rows->flatMap(fn (array $warehouse) => $warehouse['stock_lines']);
        $allUnassigned = $rows->flatMap(fn (array $warehouse) => $warehouse['unassigned']);

        $summary = [
            'stock_lines' => $allStockLines->count(),
            'located_lines' => $allStockLines->where('status', 'OK')->count(),
            'unlocated_lines' => $allUnassigned->count(),
            'variance_lines' => $allStockLines->whereIn('status', ['PARTIAL', 'OVER'])->count(),
            'frozen_bins' => $locations->filter(fn (WarehouseLocation $location) => $lotsByLocation
                ->get($location->id, collect())
                ->whereIn('status', [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED])
                ->isNotEmpty())->count(),
            'located_items' => $lots->count() + $products->count() + $assignedMaterialStocks->count(),
        ];

        if ($request->boolean('export')) {
            return $this->exportReconcileCsv($rows);
        }

        return Inertia::render('admin/warehouses/LocationReconcile', [
            'warehouses' => $rows,
            'summary' => $summary,
            'filters' => ['warehouse' => $warehouseId ?: ''],
        ]);
    }

    private function exportReconcileCsv($rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['仓库', '库存行 ID', '类型', '编码', '名称', '账面数量', '单位', '库位合计', '差异', '未定位', '状态', '库位编码', '批次数', '冻结批次数']);

            $statusLabels = [
                'OK' => '已对平',
                'UNLOCATED' => '未指定库位',
                'PARTIAL' => '部分定位',
                'OVER' => '超出账面',
            ];

            foreach ($rows as $warehouse) {
                foreach ($warehouse['stock_lines'] as $line) {
                    fputcsv($out, [
                        $warehouse['name'],
                        $line['id'],
                        $line['kind'] === 'product' ? '成品' : '物料库存',
                        $line['code'],
                        $line['name'],
                        $line['balance'],
                        $line['uom'],
                        $line['located'],
                        $line['variance'],
                        $line['unlocated'],
                        $statusLabels[$line['status']] ?? $line['status'],
                        implode(' / ', $line['location_codes']),
                        $line['lot_count'],
                        $line['held_lot_count'],
                    ]);
                }
            }

            fclose($out);
        }, 'location-reconcile-'.now()->format('Ymd-Hi').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function label(WarehouseLocation $location)
    {
        abort_unless($location->kind === WarehouseLocation::KIND_BIN, 404);

        $pdf = Pdf::loadView('admin.warehouses.location-label', [
            'labels' => [$this->labelPayload($location)],
        ])->setPaper([0, 0, 283, 198]);
        $pdf->setOption('enable_font_subsetting', true);
        $this->registerCjkFont($pdf);

        return $pdf->stream('location-'.$location->code.'.pdf');
    }

    public function labels(Request $request)
    {
        $warehouseId = (int) $request->query('warehouse', 0);

        $locations = WarehouseLocation::query()
            ->where('kind', WarehouseLocation::KIND_BIN)
            ->where('is_active', true)
            ->when($warehouseId > 0, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->with(['warehouse', 'parent'])
            ->orderBy('warehouse_id')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        abort_if($locations->isEmpty(), 404);

        $pdf = Pdf::loadView('admin.warehouses.location-label', [
            'labels' => $locations->map(fn (WarehouseLocation $location) => $this->labelPayload($location))->all(),
        ])->setPaper([0, 0, 283, 198]);
        $pdf->setOption('enable_font_subsetting', true);
        $this->registerCjkFont($pdf);

        return $pdf->stream('location-labels.pdf');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'kind' => 'required|in:zone,bin',
            'parent_id' => 'nullable|integer|exists:warehouse_locations,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:200',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]);

        $warehouse = Warehouse::query()->whereKey($data['warehouse_id'])->where('is_active', true)->first();

        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => '这个仓库不存在或已停用。']);
        }

        $parent = $this->resolveParent($data);
        $this->assertUniqueCode($warehouse->id, $data['code']);

        $location = WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'parent_id' => $parent?->id,
            'kind' => $data['kind'],
            'code' => trim($data['code']),
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('success', ($location->kind === WarehouseLocation::KIND_ZONE ? '库区' : '库位').'已创建。');
    }

    public function update(Request $request, WarehouseLocation $location)
    {
        $data = $request->validate([
            'kind' => 'required|in:zone,bin',
            'parent_id' => 'nullable|integer|exists:warehouse_locations,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:200',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]);

        if ($data['kind'] !== $location->kind) {
            throw ValidationException::withMessages(['kind' => '库区与库位不能互相转换，请新建一条。']);
        }

        $parent = $this->resolveParent($data, $location);
        $this->assertUniqueCode($location->warehouse_id, $data['code'], $location->id);

        $isActive = (bool) ($data['is_active'] ?? true);

        if (! $isActive) {
            $this->assertCanDeactivate($location);
        }

        $location->update([
            'parent_id' => $parent?->id,
            'code' => trim($data['code']),
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $isActive,
        ]);

        return back()->with('success', ($location->kind === WarehouseLocation::KIND_ZONE ? '库区' : '库位').'已更新。');
    }

    private function registerCjkFont(\Barryvdh\DomPDF\PDF $pdf): void
    {
        $regular = public_path('fonts/NotoSansSC-Regular.ttf');
        if (! is_file($regular)) {
            return;
        }

        $metrics = $pdf->getDomPDF()->getFontMetrics();
        $metrics->registerFont(['family' => 'CoaNotoSans', 'style' => 'normal', 'weight' => 'normal'], $regular);
        $bold = public_path('fonts/NotoSansSC-Bold.ttf');
        if (is_file($bold)) {
            $metrics->registerFont(['family' => 'CoaNotoSans', 'style' => 'normal', 'weight' => 'bold'], $bold);
        }
    }

    private function labelPayload(WarehouseLocation $location): array
    {
        $location->loadMissing(['warehouse', 'parent']);

        $url = route('admin.warehouses.locations.index', ['warehouse' => $location->warehouse_id]).'#bin-'.$location->id;
        $qr = (new PngWriter())
            ->write(QrCode::create($url)
                ->setSize(180)
                ->setMargin(1)
                ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium))
            ->getDataUri();

        return [
            'code' => $location->code,
            'name' => $location->name,
            'description' => $location->description,
            'warehouse_name' => $location->warehouse?->name,
            'warehouse_code' => $location->warehouse?->code,
            'zone_code' => $location->parent?->code,
            'zone_name' => $location->parent?->name,
            'qr' => $qr,
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    private function resolveParent(array $data, ?WarehouseLocation $location = null): ?WarehouseLocation
    {
        if ($data['kind'] === WarehouseLocation::KIND_ZONE) {
            return null;
        }

        $parent = WarehouseLocation::query()
            ->whereKey($data['parent_id'] ?? null)
            ->first();

        if (! $parent || $parent->kind !== WarehouseLocation::KIND_ZONE) {
            throw ValidationException::withMessages(['parent_id' => '库位必须挂在一个库区下。']);
        }

        $warehouseId = $location?->warehouse_id ?? ($data['warehouse_id'] ?? null);

        if ($warehouseId && (int) $parent->warehouse_id !== (int) $warehouseId) {
            throw ValidationException::withMessages(['parent_id' => '库区与库位必须属于同一个仓库。']);
        }

        return $parent;
    }

    private function assertUniqueCode(int $warehouseId, string $code, ?int $ignoreId = null): void
    {
        $duplicate = WarehouseLocation::withTrashed()
            ->where('warehouse_id', $warehouseId)
            ->whereNull('deleted_at')
            ->where('code', trim($code))
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['code' => '同一仓库下已有这个库位编码。']);
        }
    }

    private function assertCanDeactivate(WarehouseLocation $location): void
    {
        if ($location->children()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['is_active' => '这个库区下还有启用中的子库位，先停用子库位。']);
        }

        $occupiedLot = MaterialLot::query()
            ->where('warehouse_location_id', $location->id)
            ->where(function ($query) {
                $query->where('quantity_available', '>', 0)
                    ->orWhereIn('status', [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED]);
            })
            ->exists();

        $occupiedProduct = WarehouseStock::query()
            ->where('warehouse_location_id', $location->id)
            ->whereNotNull('product_type_id')
            ->exists();

        if ($occupiedLot || $occupiedProduct) {
            throw ValidationException::withMessages(['is_active' => '这个库位还有库存，不能停用。']);
        }
    }
}
