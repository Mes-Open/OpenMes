<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StorePurchaseOrderRequest;
use App\Models\Company;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchase commitments and their delivery progress.
 *
 * A purchase order is the commercial commitment; a receipt is a warehouse
 * document. The controller derives received quantities from posted stock-document
 * lines, never from a copied counter on the order.
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'supplier' => $request->integer('supplier') ?: null,
            'status' => $request->string('status')->toString(),
            'search' => trim($request->string('search')->toString()),
        ];

        $orders = PurchaseOrder::query()
            ->with([
                'supplier:id,code,name',
                'lines.material:id,code,name,unit_of_measure',
            ])
            ->when($filters['supplier'], fn ($query, $supplier) => $query->where('supplier_id', $supplier))
            ->when(in_array($filters['status'], PurchaseOrder::STATUSES, true), fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $lineIds = $orders->flatMap(fn (PurchaseOrder $order) => $order->lines->pluck('id'))->unique()->all();
        $receivedByLine = StockDocumentLine::query()
            ->whereIn('purchase_order_line_id', $lineIds)
            ->whereHas('stockDocument', fn ($query) => $query->where('status', StockDocument::STATUS_POSTED))
            ->selectRaw('purchase_order_line_id, SUM(quantity) as received')
            ->groupBy('purchase_order_line_id')
            ->pluck('received', 'purchase_order_line_id')
            ->all();

        $orderRows = $orders->map(fn (PurchaseOrder $order) => $this->orderRow($order, $receivedByLine));

        $openOrders = PurchaseOrder::query()->open()->with('lines')->get();
        $summary = [
            'open_orders' => $openOrders->count(),
            'overdue_orders' => $openOrders->filter->isOverdue()->count(),
            'open_value' => $openOrders->sum(fn (PurchaseOrder $order) => $order->orderedTotal()),
            'suppliers' => Company::query()->active()->suppliers()->count(),
        ];

        return Inertia::render('admin/purchasing/Orders', [
            'orders' => $orderRows,
            'summary' => $summary,
            'suppliers' => Company::query()
                ->active()
                ->suppliers()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'materials' => Material::query()
                ->where('is_active', true)
                ->with('materialType:id,code,name')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'unit_of_measure', 'material_type_id']),
            'filters' => $filters,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $number = $this->nextNumber();
        $materials = Material::query()
            ->whereIn('id', collect($data['lines'])->pluck('material_id'))
            ->get()
            ->keyBy('id');

        $order = DB::transaction(function () use ($data, $number, $materials, $request) {
            $order = PurchaseOrder::create([
                'number' => $number,
                'supplier_id' => $data['supplier_id'],
                'status' => PurchaseOrder::STATUS_OPEN,
                'ordered_at' => $data['ordered_at'] ?? today(),
                'expected_at' => $data['expected_at'] ?? null,
                'currency' => $data['currency'] ?? 'CNY',
                'notes' => $data['notes'] ?? null,
                'created_by_id' => $request->user()?->id,
            ]);

            foreach (array_values($data['lines']) as $index => $line) {
                $material = $materials->get($line['material_id']);

                $order->lines()->create([
                    'line_no' => $index + 1,
                    'material_id' => $line['material_id'],
                    'quantity_ordered' => $line['quantity_ordered'],
                    'unit_price' => $line['unit_price'] ?? 0,
                    'unit_of_measure' => $material?->unit_of_measure,
                    'expected_at' => $line['expected_at'] ?? $order->expected_at,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $order;
        });

        return back()->with('success', "采购订单 {$order->number} 已创建。");
    }

    public function close(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_OPEN) {
            return back()->with('error', '只有待收采购订单可以关闭。');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return back()->with('success', "采购订单 {$purchaseOrder->number} 已关闭。");
    }

    /** @param array<int|string, mixed> $receivedByLine */
    private function orderRow(PurchaseOrder $order, array $receivedByLine): array
    {
        $lines = $order->lines->map(function ($line) use ($receivedByLine) {
            $received = (float) ($receivedByLine[$line->id] ?? 0);
            $remaining = max(0, (float) $line->quantity_ordered - $received);

            return [
                'id' => $line->id,
                'material_id' => $line->material_id,
                'material_code' => $line->material?->code,
                'material_name' => $line->material?->name ?? '—',
                'unit_of_measure' => $line->unit_of_measure ?: $line->material?->unit_of_measure,
                'quantity_ordered' => (float) $line->quantity_ordered,
                'quantity_received' => $received,
                'quantity_remaining' => $remaining,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->quantity_ordered * (float) $line->unit_price,
                'expected_at' => $line->expected_at?->toDateString(),
            ];
        });

        $ordered = (float) $lines->sum('quantity_ordered');
        $received = (float) $lines->sum('quantity_received');
        $remaining = (float) $lines->sum('quantity_remaining');
        $deliveryStatus = match (true) {
            $order->status === PurchaseOrder::STATUS_CANCELLED => '已取消',
            $order->status === PurchaseOrder::STATUS_CLOSED => '已关闭',
            $remaining <= 0 => '已收齐',
            $received > 0 => '部分到货',
            default => '待收货',
        };

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'delivery_status' => $deliveryStatus,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier?->name ?? '—',
            'ordered_at' => $order->ordered_at?->toDateString(),
            'expected_at' => $order->expected_at?->toDateString(),
            'currency' => $order->currency,
            'notes' => $order->notes,
            'lines' => $lines,
            'ordered_quantity' => $ordered,
            'received_quantity' => $received,
            'remaining_quantity' => $remaining,
            'ordered_value' => (float) $lines->sum('line_total'),
            'overdue' => $order->status === PurchaseOrder::STATUS_OPEN
                && $order->expected_at !== null
                && $order->expected_at->lt(today()),
        ];
    }

    private function nextNumber(): string
    {
        $year = now()->year;
        $prefix = "PO/{$year}/";
        $last = PurchaseOrder::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');
        $sequence = $last ? ((int) substr($last, strrpos($last, '/') + 1)) + 1 : 1;

        return sprintf('PO/%d/%04d', $year, $sequence);
    }
}
