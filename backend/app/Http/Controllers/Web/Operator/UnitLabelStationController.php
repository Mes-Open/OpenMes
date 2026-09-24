<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\SerialUnit;
use App\Models\WorkOrder;
use App\Services\Traceability\SerialTraceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitLabelStationController extends Controller
{
    public function __construct(
        private readonly SerialTraceService $serials,
    ) {
    }

    /**
     * Operator station screen for applying pre-printed serial labels to units.
     */
    public function index(): Response
    {
        $workOrders = WorkOrder::whereIn('status', ['IN_PROGRESS', 'PENDING', 'ACCEPTED'])
            ->with('productType:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (WorkOrder $wo) => [
                'id' => $wo->id,
                'order_no' => $wo->order_no,
                'product' => $wo->productType?->name ?? '',
                'status' => $wo->status,
            ]);

        return Inertia::render('operator/unit-labels/Station', [
            'workOrders' => $workOrders,
        ]);
    }

    /**
     * Bind a scanned serial number to its process serial number. Creates the
     * unit on first sight, updates on re-scan.
     */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'serial_no' => ['required', 'string', 'max:100'],
            'psn' => ['nullable', 'string', 'max:100'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
        ]);

        $serialNo = trim((string) $data['serial_no']);
        $psn = isset($data['psn']) && trim((string) $data['psn']) !== '' ? trim((string) $data['psn']) : null;
        $workOrderId = $data['work_order_id'] ?? null;

        $unit = SerialUnit::where('serial_no', $serialNo)->first();

        if ($unit !== null) {
            $changed = false;
            if ($psn !== null && $unit->psn !== $psn) {
                $unit->psn = $psn;
                $changed = true;
            }
            if ($workOrderId !== null && empty($unit->work_order_id)) {
                $unit->work_order_id = $workOrderId;
                $changed = true;
            }
            if ($changed) {
                $unit->save();
            }
            $created = false;
            $message = 'Unit already registered, label binding confirmed.';
        } else {
            $unit = $this->serials->registerUnit($serialNo, [
                'psn' => $psn,
                'work_order_id' => $workOrderId,
                'status' => SerialUnit::STATUS_IN_PRODUCTION,
            ]);
            $created = true;
            $message = 'Unit registered, PSN bound to serial number.';
        }

        return response()->json([
            'created' => $created,
            'unit' => $this->payload($unit),
            'message' => $message,
        ]);
    }

    /**
     * Recent units for the station screen, optionally filtered by work order.
     */
    public function units(Request $request): JsonResponse
    {
        $workOrderId = $request->integer('work_order_id') ?: null;

        $units = SerialUnit::query()
            ->when($workOrderId, fn ($q) => $q->where('work_order_id', $workOrderId))
            ->with('workOrder:id,order_no')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SerialUnit $u) => $this->payload($u));

        return response()->json(['units' => $units]);
    }

    private function payload(SerialUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'serial_no' => $unit->serial_no,
            'psn' => $unit->psn,
            'status' => $unit->status,
            'work_order' => $unit->workOrder?->order_no,
            'produced_at' => $unit->produced_at?->toIso8601String(),
        ];
    }
}
