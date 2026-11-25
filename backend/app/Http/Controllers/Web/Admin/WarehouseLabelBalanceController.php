<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchReleaseCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * P1 warehouse label balance: issued / consumed / scrapped / returned labels
 * per batch. A non-zero difference is recorded as a FAIL LABEL_BALANCE check,
 * so the existing G09 release gate in CoaService blocks the batch without a
 * second approval path.
 */
class WarehouseLabelBalanceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $batches = Batch::query()
            ->with(['workOrder.productType', 'workOrder.customer'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('batch_number', 'like', "%{$q}%")
                        ->orWhereHas('workOrder.productType', fn ($product) => $product->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('workOrder', fn ($wo) => $wo->where('order_no', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $latestChecks = BatchReleaseCheck::query()
            ->where('check_type', BatchReleaseCheck::TYPE_LABEL_BALANCE)
            ->whereIn('batch_id', $batches->pluck('id'))
            ->with('checkedBy')
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get()
            ->unique('batch_id')
            ->keyBy('batch_id');

        $rows = $batches
            ->map(fn (Batch $batch) => $this->row($batch, $latestChecks->get($batch->id)))
            ->sortBy(fn (array $row) => match ($row['gate']) {
                'BLOCK' => 0,
                'MISSING' => 1,
                default => 2,
            } * 100000 - $row['id'])
            ->values();

        $summary = [
            'total' => $rows->count(),
            'balanced' => $rows->where('gate', 'PASS')->count(),
            'variance' => $rows->where('gate', 'BLOCK')->count(),
            'missing' => $rows->where('gate', 'MISSING')->count(),
            'issued' => $rows->sum('issued'),
            'consumed' => $rows->sum('consumed'),
            'scrapped' => $rows->sum('scrapped'),
            'returned' => $rows->sum('returned'),
            'variance_total' => $rows->sum('variance'),
        ];

        $visible = $rows
            ->when($status === 'variance', fn ($items) => $items->where('gate', 'BLOCK'))
            ->when($status === 'balanced', fn ($items) => $items->where('gate', 'PASS'))
            ->when($status === 'missing', fn ($items) => $items->where('gate', 'MISSING'))
            ->values();

        return Inertia::render('admin/warehouses/Index', [
            'rows' => $visible,
            'summary' => $summary,
            'filters' => ['q' => $q, 'status' => $status],
        ]);
    }

    public function store(Request $request, Batch $batch)
    {
        if ($batch->released_at !== null) {
            throw ValidationException::withMessages([
                'issued' => '批次已放行，标签平衡只能通过异常处置流程更正，不能直接改放行记录。',
            ]);
        }

        $data = $request->validate([
            'issued' => ['required', 'integer', 'min:0', 'max:99999999'],
            'consumed' => ['required', 'integer', 'min:0', 'max:99999999'],
            'scrapped' => ['required', 'integer', 'min:0', 'max:99999999'],
            'returned' => ['required', 'integer', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $issued = (float) $data['issued'];
        $consumed = (float) $data['consumed'];
        $scrapped = (float) $data['scrapped'];
        $returned = (float) $data['returned'];
        $variance = round($issued - $consumed - $scrapped - $returned, 3);
        $balanced = abs($variance) < 0.0001;

        BatchReleaseCheck::create([
            'batch_id' => $batch->id,
            'check_type' => BatchReleaseCheck::TYPE_LABEL_BALANCE,
            'status' => $balanced ? BatchReleaseCheck::STATUS_PASS : BatchReleaseCheck::STATUS_FAIL,
            'checked_by_id' => Auth::id(),
            'checked_at' => now(),
            'evidence' => "领用 {$issued} / 消耗 {$consumed} / 报废 {$scrapped} / 退回 {$returned}",
            'metadata' => [
                'issued' => $issued,
                'consumed' => $consumed,
                'scrapped' => $scrapped,
                'returned' => $returned,
                'variance' => $variance,
                'notes' => $data['notes'] ?? null,
            ],
        ]);

        if (! $balanced) {
            return back()->with(
                'error',
                "标签差异 {$variance} 张未平：G09 标签平衡已记为 FAIL，批次放行会被阻断。请核对领用、消耗、报废和退回后再登记。"
            );
        }

        return back()->with('success', '标签平衡已闭环：领用 = 消耗 + 报废 + 退回，G09 通过。');
    }

    private function row(Batch $batch, ?BatchReleaseCheck $check): array
    {
        $counts = $this->counts($check);
        $variance = $counts
            ? round($counts['issued'] - $counts['consumed'] - $counts['scrapped'] - $counts['returned'], 3)
            : null;

        $gate = $this->gate($check, $counts);

        return [
            'id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'order_no' => $batch->workOrder?->order_no,
            'product' => $batch->workOrder?->productType?->name ?? '—',
            'customer' => $batch->workOrder?->customer?->name ?? '—',
            'status' => $batch->status,
            'released' => $batch->released_at !== null,
            'released_at' => $batch->released_at?->toIso8601String(),
            'suggested_issued' => (float) ((float) $batch->produced_qty > 0 ? $batch->produced_qty : $batch->target_qty),
            'issued' => $counts['issued'] ?? 0,
            'consumed' => $counts['consumed'] ?? 0,
            'scrapped' => $counts['scrapped'] ?? 0,
            'returned' => $counts['returned'] ?? 0,
            'variance' => $variance ?? 0,
            'has_counts' => $counts !== null,
            'gate' => $gate,
            'check_status' => $check?->status,
            'checked_at' => $check?->checked_at?->toIso8601String(),
            'checked_by' => $check?->checkedBy?->name,
            'evidence' => $check?->evidence,
            'notes' => data_get($check?->metadata, 'notes'),
            'coa_url' => "/admin/coa/{$batch->id}",
            'slip_url' => "/admin/warehouses/label-balance/{$batch->id}/slip",
        ];
    }

    public function slip(Batch $batch)
    {
        $batch->loadMissing(['workOrder.productType', 'workOrder.customer']);

        $check = BatchReleaseCheck::query()
            ->where('batch_id', $batch->id)
            ->where('check_type', BatchReleaseCheck::TYPE_LABEL_BALANCE)
            ->with('checkedBy')
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();

        $counts = $this->counts($check);
        $gate = $this->gate($check, $counts);
        $variance = $counts
            ? round($counts['issued'] - $counts['consumed'] - $counts['scrapped'] - $counts['returned'], 3)
            : null;

        return view('admin.warehouses.label-slip', [
            'batch' => $batch,
            'counts' => $counts,
            'variance' => $variance,
            'gate' => $gate,
            'check' => $check,
            'notes' => data_get($check?->metadata, 'notes'),
            'generatedAt' => now(),
            'slipNo' => 'LB-'.$batch->batch_number.'-'.now()->format('Ymd'),
        ]);
    }

    private function gate(?BatchReleaseCheck $check, ?array $counts): string
    {
        if (! $counts) {
            return 'MISSING';
        }

        $variance = round($counts['issued'] - $counts['consumed'] - $counts['scrapped'] - $counts['returned'], 3);

        return $check?->status === BatchReleaseCheck::STATUS_PASS && abs($variance) < 0.0001
            ? 'PASS'
            : 'BLOCK';
    }

    /**
     * Prefer the structured metadata written by this page; fall back to the
     * legacy free-text evidence seeded before the balance screen existed.
     *
     * @return array{issued: float, consumed: float, scrapped: float, returned: float}|null
     */
    private function counts(?BatchReleaseCheck $check): ?array
    {
        if (! $check) {
            return null;
        }

        $metadata = is_array($check->metadata) ? $check->metadata : [];
        if (isset($metadata['issued'], $metadata['consumed'], $metadata['scrapped'])) {
            return [
                'issued' => (float) $metadata['issued'],
                'consumed' => (float) $metadata['consumed'],
                'scrapped' => (float) $metadata['scrapped'],
                'returned' => (float) ($metadata['returned'] ?? 0),
            ];
        }

        $text = (string) $check->evidence;
        if (preg_match('/领用\s*([0-9,.]+)\s*\/\s*(?:使用|消耗)\s*([0-9,.]+)\s*\/\s*报废\s*([0-9,.]+)(?:\s*\/\s*退回\s*([0-9,.]+))?/u', $text, $matches)) {
            $number = fn ($value) => (float) str_replace(',', '', $value);

            return [
                'issued' => $number($matches[1]),
                'consumed' => $number($matches[2]),
                'scrapped' => $number($matches[3]),
                'returned' => isset($matches[4]) && $matches[4] !== '' ? $number($matches[4]) : 0,
            ];
        }

        return null;
    }
}
