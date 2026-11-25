<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialLot;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * Read-only warehouse stock ledger: what sits in each warehouse, how much,
 * whether batch-tracked material actually has lots behind the aggregate, and
 * which lots are quality-held. It deliberately does not adjust quantities —
 * receipts, issues and disposals keep their own document flows.
 */
class WarehouseStockController extends Controller
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
        $q = trim((string) $request->query('q', ''));
        $warehouseId = (int) $request->query('warehouse', 0);
        $type = (string) $request->query('type', '');

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'kind']);

        $stockRows = WarehouseStock::query()
            ->with(['warehouse', 'location', 'material.materialType', 'productType'])
            ->orderBy('warehouse_id')
            ->orderBy('id')
            ->get();

        $lots = MaterialLot::query()
            ->whereIn('warehouse_id', $stockRows->pluck('warehouse_id')->unique()->values())
            ->whereIn('material_id', $stockRows->pluck('material_id')->filter()->unique()->values())
            ->with(['material:id,code,name', 'location:id,code,name,parent_id'])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();

        $lotGroups = $lots->groupBy(fn (MaterialLot $lot) => $lot->warehouse_id.':'.$lot->material_id);

        $rows = $stockRows
            ->map(fn (WarehouseStock $stock) => $this->row($stock, $lotGroups))
            ->sortBy(fn (array $row) => sprintf('%d|%s|%s', $row['status_rank'], $row['warehouse_code'], $row['item_name']))
            ->values();

        $summary = [
            'warehouses' => $warehouses->count(),
            'total' => $rows->count(),
            'material' => $rows->where('kind', 'material')->count(),
            'packaging' => $rows->where('kind', 'packaging')->count(),
            'product' => $rows->where('kind', 'product')->count(),
            'with_lots' => $rows->where('lots_count', '>', 0)->count(),
            'missing_lots' => $rows->where('status', 'NOLOT')->count(),
            'held_lots' => $rows->sum('held_lots'),
            'qty_by_uom' => $rows->groupBy('uom')->map(fn (Collection $items) => round((float) $items->sum('quantity'), 3))->all(),
        ];

        $filtered = $rows
            ->when($warehouseId > 0, fn (Collection $items) => $items->where('warehouse_id', $warehouseId))
            ->when($type !== '', fn (Collection $items) => $items->where('kind', $type))
            ->when($q !== '', function (Collection $items) use ($q) {
                $needle = mb_strtolower($q);

                return $items->filter(function (array $row) use ($needle) {
                    return str_contains(mb_strtolower(implode(' ', [
                        $row['item_name'],
                        $row['item_code'],
                        $row['warehouse_name'],
                        $row['warehouse_code'],
                    ])), $needle);
                });
            })
            ->values();

        return Inertia::render('admin/warehouses/Stock', [
            'rows' => $filtered,
            'summary' => $summary,
            'warehouses' => $warehouses,
            'filters' => [
                'q' => $q,
                'warehouse' => $warehouseId ?: '',
                'type' => $type,
            ],
        ]);
    }

    private function row(WarehouseStock $stock, Collection $lotGroups): array
    {
        $material = $stock->material;
        $product = $stock->productType;
        $materialTypeCode = $material?->materialType?->code;
        $kind = $product ? 'product' : ($materialTypeCode === 'packaging' ? 'packaging' : 'material');
        $tracking = $material?->tracking_type ?? 'none';
        $typeLabel = $product ? '成品' : match ($materialTypeCode) {
            'raw_material' => '原料',
            'packaging' => '包材',
            'semi_finished' => '半成品',
            'auxiliary' => '辅料',
            default => $material?->materialType?->name ?? '物料',
        };

        $stockLots = collect();
        if ($material && $stock->warehouse_id) {
            $stockLots = $lotGroups->get($stock->warehouse_id.':'.$stock->material_id, collect());
            if ($stock->material_lot_id) {
                $stockLots = $stockLots->where('id', $stock->material_lot_id)->values();
            }
        }

        $heldLots = $stockLots->whereIn('status', [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED]);
        $availableOnLots = $stockLots->where('status', MaterialLot::STATUS_RELEASED)->sum(fn (MaterialLot $lot) => (float) $lot->quantity_available);
        $nextExpiry = $stockLots
            ->filter(fn (MaterialLot $lot) => $lot->expiry_date !== null && ! $lot->expiry_date->isPast())
            ->sortBy(fn (MaterialLot $lot) => $lot->expiry_date->timestamp)
            ->first();

        $stockLocations = $product
            ? collect([$stock->location])->filter()
            : $stockLots->pluck('location')->filter()->unique('id')->values();

        $status = match (true) {
            // A rejected/quarantine lot stays physically in the warehouse even
            // when the aggregate says zero: surface the quality hold first.
            $heldLots->isNotEmpty() => 'HOLD',
            (float) $stock->quantity <= 0 => 'ZERO',
            $tracking === 'batch' && $stockLots->isEmpty() => 'NOLOT',
            default => 'OK',
        };

        return [
            'id' => $stock->id,
            'warehouse_id' => $stock->warehouse_id,
            'warehouse_code' => $stock->warehouse?->code ?? '—',
            'warehouse_name' => $stock->warehouse?->name ?? '—',
            'warehouse_kind' => $stock->warehouse?->kind,
            'item_code' => $material?->code ?? $product?->code ?? '—',
            'item_name' => $material?->name ?? $product?->name ?? '—',
            'item_type' => $typeLabel,
            'kind' => $kind,
            'tracking' => $tracking,
            'tracking_label' => match ($tracking) {
                'batch' => '批次跟踪',
                'serial' => '序列号跟踪',
                default => '不跟踪批次',
            },
            'quantity' => (float) $stock->quantity,
            'uom' => $stock->unit_of_measure,
            'lots_count' => $stockLots->count(),
            'lots_available' => round($availableOnLots, 3),
            'held_lots' => $heldLots->count(),
            'next_expiry' => $nextExpiry?->expiry_date?->toDateString(),
            'location_count' => $stockLocations->count(),
            'location_summary' => $stockLocations->isEmpty() ? null : $stockLocations->map(fn ($location) => $location->code)->implode(' · '),
            'status' => $status,
            'status_rank' => match ($status) {
                'HOLD' => 0,
                'NOLOT' => 1,
                'OK' => 2,
                default => 3,
            },
            'status_label' => match (true) {
                $status === 'ZERO' => '零库存',
                $status === 'HOLD' => "冻结 {$heldLots->count()} 批",
                $status === 'NOLOT' => '缺批次',
                $tracking === 'batch' => '批次齐全',
                default => '在库',
            },
            'lot_summary' => match (true) {
                $tracking === 'none' => '不跟踪批次',
                $stockLots->isEmpty() => '未登记批次',
                $heldLots->isNotEmpty() => "{$stockLots->count()} 批 · {$heldLots->count()} 冻结",
                default => "{$stockLots->count()} 批",
            },
            'lots' => $stockLots->map(fn (MaterialLot $lot) => [
                'id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'status' => $lot->status,
                'status_label' => self::LOT_STATUS_LABELS[$lot->status] ?? $lot->status,
                'received' => (float) $lot->quantity_received,
                'available' => (float) $lot->quantity_available,
                'uom' => $lot->unit_of_measure,
                'received_at' => $lot->received_at?->format('Y-m-d'),
                'expiry' => $lot->expiry_date?->toDateString(),
                'location_code' => $lot->location?->code,
                'location_name' => $lot->location?->name,
                'url' => "/admin/material-lots/{$lot->id}",
            ])->values(),
            'lots_url' => $material ? '/admin/material-lots' : null,
            'coa_url' => $product ? '/admin/coa' : null,
        ];
    }
}
