<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Supplier reconciliation across purchase-order lines.
 *
 * One row is one commitment line. Ordered comes from the purchase order,
 * received from posted stock-document lines, and the difference is the open
 * commitment. This deliberately reconciles by line rather than by document so
 * a partial delivery does not look like a price or quantity variance.
 */
class PurchaseReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $lines = $this->query($request)->limit(200)->get();
        $rows = $lines->map(fn (PurchaseOrderLine $line) => $this->row($line));

        $summary = [
            'open_orders' => PurchaseOrder::query()->open()->count(),
            'overdue_orders' => PurchaseOrder::query()
                ->open()
                ->whereNotNull('expected_at')
                ->whereDate('expected_at', '<', today())
                ->count(),
            'ordered_value' => (float) $rows->sum('ordered_value'),
            'received_value' => (float) $rows->sum('received_value'),
            'outstanding_value' => (float) $rows->sum('outstanding_value'),
            'open_lines' => $rows->filter(fn (array $row) => $row['quantity_remaining'] > 0)->count(),
        ];

        return Inertia::render('admin/purchasing/Reconciliation', [
            'rows' => $rows,
            'summary' => $summary,
            'suppliers' => Company::query()
                ->active()
                ->suppliers()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'filters' => $filters,
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->get()->map(fn (PurchaseOrderLine $line) => $this->row($line));
        $filename = '供应商对账-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                '供应商',
                '采购订单',
                '订单状态',
                '物料编码',
                '物料名称',
                '订购数量',
                '已收数量',
                '未收数量',
                '单位',
                '含税单价',
                '订购金额',
                '已收金额',
                '未收金额',
                '预计到货',
                '最近到货',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['supplier_name'],
                    $row['order_number'],
                    $row['order_status_label'],
                    $row['material_code'],
                    $row['material_name'],
                    $row['quantity_ordered'],
                    $row['quantity_received'],
                    $row['quantity_remaining'],
                    $row['unit_of_measure'],
                    $row['unit_price'],
                    $row['ordered_value'],
                    $row['received_value'],
                    $row['outstanding_value'],
                    $row['expected_at'],
                    $row['last_received_at'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request): Builder
    {
        $filters = $this->filters($request);

        return PurchaseOrderLine::query()
            ->with([
                'material:id,code,name,unit_of_measure',
                'purchaseOrder:id,number,status,supplier_id,ordered_at,expected_at,currency',
                'purchaseOrder.supplier:id,code,name',
                'receiptLines' => function ($query) {
                    $query->whereHas('stockDocument', fn ($document) => $document->where('status', StockDocument::STATUS_POSTED))
                        ->with('stockDocument:id,document_no,posted_at');
                },
            ])
            ->when($filters['supplier'], fn ($query, $supplier) => $query->whereHas(
                'purchaseOrder',
                fn ($order) => $order->where('supplier_id', $supplier)
            ))
            ->when($filters['status'] !== 'all', function ($query) use ($filters) {
                $query->whereHas('purchaseOrder', fn ($order) => $order->where('status', $filters['status']));
            })
            ->when($filters['status'] === 'all', function ($query) {
                $query->whereHas('purchaseOrder', fn ($order) => $order->where('status', '!=', PurchaseOrder::STATUS_CANCELLED));
            })
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('material', fn ($material) => $material
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseOrder', fn ($order) => $order
                            ->where('number', 'like', "%{$search}%")
                            ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%")));
                });
            })
            ->orderByDesc('id');
    }

    /** @return array{supplier: int|null, status: string, search: string} */
    private function filters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'supplier' => $request->integer('supplier') ?: null,
            'status' => in_array($status, ['open', 'closed', 'all'], true) ? $status : 'open',
            'search' => trim($request->string('search')->toString()),
        ];
    }

    private function row(PurchaseOrderLine $line): array
    {
        $order = $line->purchaseOrder;
        $received = (float) $line->receiptLines->sum('quantity');
        $ordered = (float) $line->quantity_ordered;
        $remaining = max(0, $ordered - $received);
        $price = (float) $line->unit_price;
        $lastReceipt = $line->receiptLines
            ->map(fn ($receiptLine) => $receiptLine->stockDocument?->posted_at)
            ->filter()
            ->max();

        if ($order?->status === PurchaseOrder::STATUS_CANCELLED) {
            $state = '已取消';
            $tone = 'gray';
        } elseif ($order?->status === PurchaseOrder::STATUS_CLOSED) {
            $state = $remaining > 0 ? '关闭时未收齐' : '已关闭';
            $tone = $remaining > 0 ? 'orange' : 'gray';
        } elseif ($remaining <= 0) {
            $state = '已收齐';
            $tone = 'green';
        } elseif ($received > 0) {
            $state = '部分到货';
            $tone = 'blue';
        } else {
            $state = '待收货';
            $tone = 'orange';
        }

        return [
            'id' => $line->id,
            'purchase_order_id' => $order?->id,
            'order_number' => $order?->number ?? '—',
            'order_status' => $order?->status,
            'order_status_label' => match ($order?->status) {
                PurchaseOrder::STATUS_CLOSED => '已关闭',
                PurchaseOrder::STATUS_CANCELLED => '已取消',
                default => '待收',
            },
            'supplier_id' => $order?->supplier_id,
            'supplier_name' => $order?->supplier?->name ?? '—',
            'ordered_at' => $order?->ordered_at?->toDateString(),
            'expected_at' => $line->expected_at?->toDateString() ?? $order?->expected_at?->toDateString(),
            'material_code' => $line->material?->code,
            'material_name' => $line->material?->name ?? '—',
            'unit_of_measure' => $line->unit_of_measure ?: $line->material?->unit_of_measure,
            'quantity_ordered' => $ordered,
            'quantity_received' => $received,
            'quantity_remaining' => $remaining,
            'unit_price' => $price,
            'ordered_value' => $ordered * $price,
            'received_value' => $received * $price,
            'outstanding_value' => $remaining * $price,
            'last_received_at' => $lastReceipt?->toDateTimeString(),
            'state' => $state,
            'state_tone' => $tone,
            'overdue' => $order?->status === PurchaseOrder::STATUS_OPEN
                && $order->expected_at !== null
                && $order->expected_at->lt(today())
                && $remaining > 0,
            'currency' => $order?->currency ?? 'CNY',
        ];
    }
}
