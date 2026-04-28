<?php

namespace Modules\Packaging\Controllers\Api;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Packaging\Models\PackagingScanLog;
use Modules\Packaging\Models\WorkOrderEan;

class PackagingApiController extends Controller
{
    // ── EAN management ──────────────────────────────────────────────────────

    public function listEans(Request $request): JsonResponse
    {
        $query = WorkOrderEan::query()->with('workOrder.productType');

        if ($woId = $request->query('work_order_id')) {
            $query->where('work_order_id', $woId);
        }
        if ($q = $request->query('q')) {
            $query->whereRaw('LOWER(ean) LIKE ?', ['%' . strtolower($q) . '%']);
        }

        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $page = $query->orderBy('ean')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function showEan(WorkOrderEan $workOrderEan): JsonResponse
    {
        $workOrderEan->load('workOrder.productType');
        return response()->json(['data' => $workOrderEan]);
    }

    public function storeEan(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['Admin', 'Supervisor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'ean' => ['required', 'string', 'max:100', 'unique:work_order_eans,ean'],
        ]);

        $ean = WorkOrderEan::create($data);

        return response()->json([
            'message' => 'EAN added',
            'data' => $ean->load('workOrder.productType'),
        ], 201);
    }

    public function destroyEan(Request $request, WorkOrderEan $workOrderEan): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['Admin', 'Supervisor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $workOrderEan->delete();
        return response()->json(['message' => 'EAN deleted']);
    }

    // ── Scan ────────────────────────────────────────────────────────────────

    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'ean' => ['required', 'string', 'max:100'],
        ]);

        $eanRecord = WorkOrderEan::where('ean', $request->ean)->first();
        if (!$eanRecord) {
            return response()->json(['message' => 'Unknown EAN'], 404);
        }

        $workOrder = WorkOrder::find($eanRecord->work_order_id);
        if (!$workOrder) {
            return response()->json(['message' => 'Work order not found'], 404);
        }

        $user = $request->user();
        if ($user->hasRole('Operator')) {
            $hasAccess = $user->lines()->where('lines.id', $workOrder->line_id)->exists();
            if (!$hasAccess) {
                return response()->json(['message' => 'You do not have access to this line.'], 403);
            }
        }

        if (!in_array($workOrder->status, [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS], true)) {
            return response()->json([
                'message' => "Work order not in a packable state (current: {$workOrder->status})",
            ], 422);
        }

        $planned = (int) $workOrder->planned_qty;
        if ($planned > 0 && $workOrder->packed_qty >= $planned) {
            return response()->json(['message' => 'Work order fully packed'], 422);
        }

        $workOrder->increment('packed_qty');
        $workOrder->refresh();

        $log = PackagingScanLog::create([
            'user_id' => $request->user()?->id,
            'work_order_id' => $workOrder->id,
            'ean' => $request->ean,
            'product_name' => $this->productLabel($workOrder),
            'scanned_at' => now(),
        ]);

        return response()->json([
            'message' => 'Packed: ' . $this->productLabel($workOrder),
            'data' => [
                'work_order' => [
                    'id' => $workOrder->id,
                    'order_no' => $workOrder->order_no,
                    'product' => $this->productLabel($workOrder),
                    'planned_qty' => $planned,
                    'packed_qty' => $workOrder->packed_qty,
                    'status' => $workOrder->status,
                ],
                'scan' => [
                    'id' => $log->id,
                    'ean' => $log->ean,
                    'scanned_at' => $log->scanned_at->toIso8601String(),
                ],
            ],
        ]);
    }

    // ── Scan logs ───────────────────────────────────────────────────────────

    public function scanLogs(Request $request): JsonResponse
    {
        $query = PackagingScanLog::query()->with('user', 'workOrder');

        if ($woId = $request->query('work_order_id')) {
            $query->where('work_order_id', $woId);
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($from = $request->query('from')) {
            $query->where('scanned_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('scanned_at', '<=', $to);
        }

        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $page = $query->orderByDesc('scanned_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    // ── Packaging dashboard data ────────────────────────────────────────────

    public function items(): JsonResponse
    {
        $eansByWorkOrder = WorkOrderEan::select('work_order_id', 'ean')
            ->get()
            ->groupBy('work_order_id');

        $items = WorkOrder::whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])
            ->with('productType', 'line')
            ->orderByDesc('priority')
            ->get()
            ->filter(fn($wo) => $eansByWorkOrder->has($wo->id))
            ->map(function ($wo) use ($eansByWorkOrder) {
                $planned = (int) $wo->planned_qty;
                $packed = (int) $wo->packed_qty;
                return [
                    'id' => $wo->id,
                    'order_no' => $wo->order_no,
                    'product' => $this->productLabel($wo),
                    'line' => $wo->line?->name,
                    'planned_qty' => $planned,
                    'packed_qty' => $packed,
                    'progress' => $planned > 0 ? min(100, (int) round($packed / $planned * 100)) : 0,
                    'done' => $planned > 0 && $packed >= $planned,
                    'eans' => $eansByWorkOrder[$wo->id]->pluck('ean')->values(),
                    'status' => $wo->status,
                ];
            })
            ->values()
            ->toArray();

        return response()->json(['data' => $items]);
    }

    public function stats(): JsonResponse
    {
        $shiftStart = $this->currentShiftStart();
        $todayPacked = PackagingScanLog::where('scanned_at', '>=', $shiftStart)->count();

        $plan = WorkOrder::whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])
            ->whereHas('eans')
            ->sum('planned_qty');

        $totalPacked = WorkOrder::whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])
            ->whereHas('eans')
            ->sum('packed_qty');

        return response()->json([
            'data' => [
                'today_packed' => $todayPacked,
                'plan' => (int) $plan,
                'total_packed' => (int) $totalPacked,
                'backlog' => max(0, (int) $plan - (int) $totalPacked),
                'shift_start' => $shiftStart->toIso8601String(),
            ],
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function productLabel(WorkOrder $wo): string
    {
        $parts = array_filter([$wo->productType?->name, $wo->order_no]);
        return implode(' — ', $parts) ?: $wo->order_no;
    }

    private function currentShiftStart(): Carbon
    {
        $now = Carbon::now();
        $hour = $now->hour;
        if ($hour >= 6 && $hour < 18) return $now->copy()->setTime(6, 0, 0);
        if ($hour >= 18) return $now->copy()->setTime(18, 0, 0);
        return $now->copy()->subDay()->setTime(18, 0, 0);
    }
}
