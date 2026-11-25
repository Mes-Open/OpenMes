<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreMaterialReceiptRequest;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Material\ManualMaterialReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 采购到货工作台：手工到货流水 + 现有待检批次。
 *
 * 手工到货只写 StockMovement，不创建 MaterialLot；因此这里把“到货流水”和
 * “待检/冻结批次”分成两个数据集展示，避免把同一个动作误读成批次入库。
 */
class MaterialReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $warehouseId = $request->integer('warehouse') ?: null;

        $receipts = StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RECEIPT)
            ->whereNotNull('material_id')
            ->with([
                'material:id,code,name,unit_of_measure,stock_quantity,is_active',
                'performedBy:id,name',
                'warehouse:id,code,name',
            ])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByDesc('performed_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(function (StockMovement $movement) {
                $source = (string) $movement->source_type;

                return [
                    'id' => $movement->id,
                    'material_id' => $movement->material_id,
                    'material_code' => $movement->material?->code,
                    'material_name' => $movement->material?->name ?? '—',
                    'quantity' => (float) $movement->quantity,
                    'uom' => $movement->material?->unit_of_measure,
                    'reference' => $movement->reason,
                    'source_type' => $source,
                    'source_label' => match ($source) {
                        'manual_receipt' => '手工到货',
                        StockMovement::SOURCE_RECEIPT => '采购到货',
                        StockMovement::SOURCE_STOCK_DOCUMENT => '仓储入库',
                        StockMovement::SOURCE_INSPECTION => '检验入库',
                        default => $source !== '' ? $source : '其他',
                    },
                    'received_at' => $movement->performed_at?->toIso8601String(),
                    'warehouse' => $movement->warehouse
                        ? ['id' => $movement->warehouse->id, 'code' => $movement->warehouse->code, 'name' => $movement->warehouse->name]
                        : null,
                    'operator' => $movement->performedBy?->name,
                ];
            });

        $pendingLots = MaterialLot::query()
            ->whereIn('status', [MaterialLot::STATUS_RECEIVED, MaterialLot::STATUS_QUARANTINE])
            ->with([
                'material:id,code,name,unit_of_measure',
                'warehouse:id,code,name',
                'location:id,code,name',
            ])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByRaw("CASE WHEN status = 'quarantine' THEN 0 ELSE 1 END")
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(function (MaterialLot $lot) {
                return [
                    'id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'material_id' => $lot->material_id,
                    'material_code' => $lot->material?->code,
                    'material_name' => $lot->material?->name ?? '—',
                    'supplier_name' => $lot->supplier_name,
                    'supplier_lot_no' => $lot->supplier_lot_no,
                    'quantity_received' => (float) $lot->quantity_received,
                    'uom' => $lot->unit_of_measure ?: $lot->material?->unit_of_measure,
                    'received_at' => $lot->received_at?->toIso8601String(),
                    'warehouse' => $lot->warehouse?->name,
                    'location' => $lot->location
                        ? trim($lot->location->code.' '.$lot->location->name)
                        : null,
                    'status' => $lot->status,
                    'status_label' => $lot->status === MaterialLot::STATUS_QUARANTINE ? '冻结' : '待检',
                    'status_tone' => $lot->status === MaterialLot::STATUS_QUARANTINE ? 'red' : 'orange',
                    'material_url' => route('materials.show', $lot->material_id, false),
                    'lot_url' => route('material-lots.show', $lot->id, false),
                ];
            });

        $today = now()->startOfDay();
        $summary = [
            'today_receipts' => StockMovement::query()
                ->where('movement_type', StockMovement::TYPE_RECEIPT)
                ->whereNotNull('material_id')
                ->where('performed_at', '>=', $today)
                ->count(),
            'week_receipts' => StockMovement::query()
                ->where('movement_type', StockMovement::TYPE_RECEIPT)
                ->whereNotNull('material_id')
                ->where('performed_at', '>=', now()->subDays(7))
                ->count(),
            'pending_lots' => MaterialLot::query()->where('status', MaterialLot::STATUS_RECEIVED)->count(),
            'hold_lots' => MaterialLot::query()->where('status', MaterialLot::STATUS_QUARANTINE)->count(),
            'active_materials' => Material::query()->where('is_active', true)->count(),
        ];

        return Inertia::render('admin/purchasing/Receipts', [
            'receipts' => $receipts,
            'pendingLots' => $pendingLots,
            'summary' => $summary,
            'warehouses' => Warehouse::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'materials' => Material::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'unit_of_measure', 'stock_quantity', 'tracking_type']),
            'filters' => ['warehouse' => $warehouseId],
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreMaterialReceiptRequest $request, ManualMaterialReceiptService $service): RedirectResponse
    {
        $data = $request->validated();
        $material = Material::query()->findOrFail($data['material_id']);

        $movement = $service->receive(
            $material,
            (float) $data['quantity'],
            trim((string) $data['reference']),
            $request->user(),
        );

        $quantity = rtrim(rtrim((string) $movement->quantity, '0'), '.');
        $unit = match ($material->unit_of_measure) {
            'pcs' => '件',
            'l' => 'L',
            default => $material->unit_of_measure,
        };
        $reference = trim((string) $movement->reason);
        $suffix = $reference !== '' ? "（参考 {$reference}）" : '';

        return back()->with('success', "已登记到货：{$material->name} {$quantity} {$unit}{$suffix}。");
    }
}
