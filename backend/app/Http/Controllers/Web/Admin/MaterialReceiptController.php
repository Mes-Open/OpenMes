<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreMaterialReceiptRequest;
use App\Http\Requests\Web\Admin\StorePurchaseReceiptRequest;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\PurchaseOrder;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Material\ManualMaterialReceiptService;
use App\Services\Warehouse\StockDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $purchaseOrderId = $request->integer('purchase_order') ?: null;

        $openPurchaseOrders = PurchaseOrder::query()
            ->open()
            ->with('supplier:id,code,name')
            ->withCount('lines')
            ->orderByRaw('expected_at IS NULL')
            ->orderBy('expected_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'supplier_name' => $order->supplier?->name ?? '—',
                'expected_at' => $order->expected_at?->toDateString(),
                'lines_count' => $order->lines_count,
            ]);

        $selectedPurchaseOrder = null;
        if ($purchaseOrderId) {
            $selectedPurchaseOrder = $this->purchaseOrderPayload($purchaseOrderId);
        }

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
            'materialWarehouses' => Warehouse::query()
                ->where('is_active', true)
                ->forMaterials()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'kind', 'is_default']),
            'materials' => Material::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'unit_of_measure', 'stock_quantity', 'tracking_type']),
            'purchaseOrders' => $openPurchaseOrders,
            'selectedPurchaseOrder' => $selectedPurchaseOrder,
            'filters' => ['warehouse' => $warehouseId, 'purchase_order' => $purchaseOrderId],
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

    /**
     * Receive against a purchase order. The stock document is the posting
     * engine: it updates the warehouse balance, the stock ledger and inbound
     * inspection in one existing transaction, while its lines carry the
     * purchase-order-line link the reconciliation reads.
     */
    public function storeOrder(StorePurchaseReceiptRequest $request, StockDocumentService $stockDocumentService): RedirectResponse
    {
        $data = $request->validated();
        $reference = trim((string) $data['reference']);

        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
        if (! $warehouse->acceptsMaterials()) {
            throw ValidationException::withMessages([
                'warehouse_id' => '所选仓库不接收原物料，请选择原料仓或混合仓。',
            ]);
        }

        $document = DB::transaction(function () use ($data, $reference, $warehouse, $request, $stockDocumentService) {
            $order = PurchaseOrder::query()
                ->whereKey($data['purchase_order_id'])
                ->lockForUpdate()
                ->first();

            if (! $order || $order->status !== PurchaseOrder::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => '该采购订单已关闭或不可收货。',
                ]);
            }

            $order->load(['supplier:id,code,name', 'lines.material:id,code,name,unit_of_measure']);

            $requested = collect($data['lines'])
                ->groupBy('purchase_order_line_id')
                ->map(fn ($rows) => (float) $rows->sum('quantity'));

            $received = $this->receivedQuantities($order->lines->pluck('id')->all());

            $receiptLines = [];
            foreach ($requested as $lineId => $quantity) {
                $line = $order->lines->firstWhere('id', (int) $lineId);
                if (! $line) {
                    throw ValidationException::withMessages([
                        'lines' => '到货明细不属于当前采购订单，请刷新后重试。',
                    ]);
                }

                $remaining = max(0, (float) $line->quantity_ordered - (float) ($received[$line->id] ?? 0));
                if ($quantity > $remaining + 0.0005) {
                    $name = $line->material?->name ?? '该物料';
                    throw ValidationException::withMessages([
                        'lines' => "{$name} 本次到货 {$quantity} 超过待收数量 {$remaining}，请调整后重试。",
                    ]);
                }

                $receiptLines[] = [
                    'purchase_order_line_id' => $line->id,
                    'material_id' => $line->material_id,
                    'quantity' => $quantity,
                    'unit_of_measure' => $line->unit_of_measure ?: $line->material?->unit_of_measure,
                    'notes' => "采购订单 {$order->number}",
                ];
            }

            $document = $stockDocumentService->createDraft([
                'type' => StockDocument::TYPE_MATERIAL_RECEIPT,
                'warehouse_id' => $warehouse->id,
                'purchase_order_id' => $order->id,
                'supplier_name' => $order->supplier?->name,
                'notes' => "采购订单 {$order->number}；供方送货单 {$reference}",
                'lines' => $receiptLines,
            ], $request->user());

            $stockDocumentService->post($document, $request->user());

            return $document;
        });

        return redirect()
            ->route('admin.purchasing.receipts.index', ['purchase_order' => $data['purchase_order_id']])
            ->with('success', "采购到货已入账：{$document->document_no}（供方参考 {$reference}）。");
    }

    private function purchaseOrderPayload(int $purchaseOrderId): ?array
    {
        $order = PurchaseOrder::query()
            ->with([
                'supplier:id,code,name',
                'lines.material:id,code,name,unit_of_measure',
            ])
            ->find($purchaseOrderId);

        if (! $order) {
            return null;
        }

        $received = $this->receivedQuantities($order->lines->pluck('id')->all());

        $lines = $order->lines->map(function ($line) use ($received) {
            $receivedQuantity = (float) ($received[$line->id] ?? 0);
            $remaining = max(0, (float) $line->quantity_ordered - $receivedQuantity);

            return [
                'id' => $line->id,
                'material_id' => $line->material_id,
                'material_code' => $line->material?->code,
                'material_name' => $line->material?->name ?? '—',
                'unit_of_measure' => $line->unit_of_measure ?: $line->material?->unit_of_measure,
                'quantity_ordered' => (float) $line->quantity_ordered,
                'quantity_received' => $receivedQuantity,
                'quantity_remaining' => $remaining,
            ];
        });

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'supplier_name' => $order->supplier?->name ?? '—',
            'ordered_at' => $order->ordered_at?->toDateString(),
            'expected_at' => $order->expected_at?->toDateString(),
            'lines' => $lines,
            'remaining_quantity' => (float) $lines->sum('quantity_remaining'),
            'can_receive' => $order->status === PurchaseOrder::STATUS_OPEN
                && $lines->contains(fn (array $line) => $line['quantity_remaining'] > 0),
        ];
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return array<int, float>
     */
    private function receivedQuantities(array $lineIds): array
    {
        if ($lineIds === []) {
            return [];
        }

        return StockDocumentLine::query()
            ->whereIn('purchase_order_line_id', $lineIds)
            ->whereHas('stockDocument', fn ($query) => $query->where('status', StockDocument::STATUS_POSTED))
            ->selectRaw('purchase_order_line_id, SUM(quantity) as received')
            ->groupBy('purchase_order_line_id')
            ->pluck('received', 'purchase_order_line_id')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();
    }
}
