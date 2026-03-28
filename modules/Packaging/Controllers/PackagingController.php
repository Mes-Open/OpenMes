<?php

namespace Modules\Packaging\Controllers;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Packaging\Models\PackagingScanLog;
use Modules\Packaging\Models\WorkOrderEan;

class PackagingController extends Controller
{
    // ── Views ─────────────────────────────────────────────────────────────────

    public function station()
    {
        return view('packaging::station');
    }

    public function adminOverview()
    {
        $items = $this->buildItemList();
        $stats = $this->buildStats();
        return view('packaging::admin', compact('items', 'stats'));
    }

    // ── JSON API (polling) ────────────────────────────────────────────────────

    public function items()
    {
        return response()->json(['items' => $this->buildItemList()]);
    }

    public function scan(Request $request)
    {
        $request->validate([
            'ean' => 'required|string|max:100',
        ]);

        $eanRecord = WorkOrderEan::where('ean', $request->ean)->first();

        if (!$eanRecord) {
            return response()->json(['message' => 'Nieznany kod EAN'], 404);
        }

        $workOrder = WorkOrder::find($eanRecord->work_order_id);

        if (!$workOrder) {
            return response()->json(['message' => 'Zlecenie nie istnieje'], 404);
        }

        if (!in_array($workOrder->status, [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])) {
            return response()->json([
                'message' => 'Zlecenie nie jest w toku ani zakończone (status: ' . $workOrder->status . ')',
            ], 422);
        }

        $planned = (int) $workOrder->planned_qty;
        if ($planned > 0 && $workOrder->packed_qty >= $planned) {
            return response()->json(['message' => 'Zlecenie już w pełni spakowane'], 422);
        }

        $workOrder->increment('packed_qty');
        $workOrder->refresh();

        PackagingScanLog::create([
            'user_id'       => $request->user()?->id,
            'work_order_id' => $workOrder->id,
            'ean'           => $request->ean,
            'product_name'  => $this->productLabel($workOrder),
            'scanned_at'    => now(),
        ]);

        return response()->json([
            'work_order' => [
                'id'         => $workOrder->id,
                'order_no'   => $workOrder->order_no,
                'product'    => $this->productLabel($workOrder),
                'planned_qty' => (int) $workOrder->planned_qty,
                'packed_qty'  => $workOrder->packed_qty,
            ],
            'message' => 'Spakowano: ' . $this->productLabel($workOrder),
        ]);
    }

    public function history()
    {
        $shiftStart = $this->currentShiftStart();

        $logs = PackagingScanLog::where('scanned_at', '>=', $shiftStart)
            ->orderByDesc('scanned_at')
            ->limit(50)
            ->get()
            ->map(fn($l) => [
                'id'           => $l->id,
                'ean'          => $l->ean,
                'product_name' => $l->product_name,
                'scanned_at'   => $l->scanned_at->format('H:i:s'),
                'after_id'     => $l->id,
            ]);

        return response()->json(['history' => $logs]);
    }

    public function historyAfter(Request $request)
    {
        $afterId = (int) $request->query('after_id', 0);

        $logs = PackagingScanLog::where('id', '>', $afterId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn($l) => [
                'id'           => $l->id,
                'ean'          => $l->ean,
                'product_name' => $l->product_name,
                'scanned_at'   => $l->scanned_at->format('H:i:s'),
            ]);

        return response()->json(['history' => $logs]);
    }

    public function stats()
    {
        return response()->json($this->buildStats());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildItemList(): array
    {
        $eansByWorkOrder = WorkOrderEan::select('work_order_id', 'ean')
            ->get()
            ->groupBy('work_order_id');

        return WorkOrder::whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])
            ->with('productType', 'line')
            ->orderByDesc('priority')
            ->get()
            ->filter(fn($wo) => $eansByWorkOrder->has($wo->id))
            ->map(function ($wo) use ($eansByWorkOrder) {
                $planned = (int) $wo->planned_qty;
                $packed  = (int) $wo->packed_qty;
                return [
                    'id'          => $wo->id,
                    'order_no'    => $wo->order_no,
                    'product'     => $this->productLabel($wo),
                    'line'        => $wo->line?->name,
                    'planned_qty' => $planned,
                    'packed_qty'  => $packed,
                    'progress'    => $planned > 0 ? min(100, (int) round($packed / $planned * 100)) : 0,
                    'done'        => $planned > 0 && $packed >= $planned,
                    'eans'        => $eansByWorkOrder[$wo->id]->pluck('ean')->values(),
                    'status'      => $wo->status,
                ];
            })
            ->values()
            ->toArray();
    }

    private function buildStats(): array
    {
        $shiftStart  = $this->currentShiftStart();
        $todayPacked = PackagingScanLog::where('scanned_at', '>=', $shiftStart)->count();

        $plan = WorkOrder::whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])
            ->whereHas('eans')
            ->sum('planned_qty');

        $totalPacked = WorkOrder::whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])
            ->whereHas('eans')
            ->sum('packed_qty');

        $backlog = max(0, (int) $plan - (int) $totalPacked);

        return [
            'today_packed' => $todayPacked,
            'plan'         => (int) $plan,
            'total_packed' => (int) $totalPacked,
            'backlog'      => $backlog,
            'shift_start'  => $shiftStart->format('H:i'),
        ];
    }

    private function productLabel(WorkOrder $wo): string
    {
        $parts = array_filter([
            $wo->productType?->name,
            $wo->order_no,
        ]);
        return implode(' — ', $parts) ?: $wo->order_no;
    }

    private function currentShiftStart(): Carbon
    {
        $now  = Carbon::now();
        $hour = $now->hour;

        if ($hour >= 6 && $hour < 18) {
            return $now->copy()->setTime(6, 0, 0);
        }
        if ($hour >= 18) {
            return $now->copy()->setTime(18, 0, 0);
        }
        return $now->copy()->subDay()->setTime(18, 0, 0);
    }
}
