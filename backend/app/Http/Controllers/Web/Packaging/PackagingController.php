<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Enums\PalletStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePalletStationRequest;
use App\Http\Requests\PackagingScanRequest;
use App\Models\PackagingScanLog;
use App\Models\Pallet;
use App\Models\WorkOrder;
use App\Models\WorkOrderEan;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PackagingController extends Controller
{
    // ── Views ─────────────────────────────────────────────────────────────────

    public function station()
    {
        // scannerMode (HID vs serial) merged from develop — passed as a prop so
        // the React Station page can read it.
        $scannerMode = json_decode(
            DB::table('system_settings')->where('key', 'scanner_mode')->value('value') ?? '"hid"',
            true
        ) ?? 'hid';

        $labelTemplates = \App\Models\LabelTemplate::where('is_active', true)
            ->where('type', \App\Models\LabelTemplate::TYPE_PALLET)
            ->get(['id', 'name', 'type', 'size', 'barcode_format', 'is_default']);

        $currentShift = $this->currentShiftPayload();

        return Inertia::render('packaging/Station', compact('scannerMode', 'labelTemplates', 'currentShift'));
    }

    public function adminOverview()
    {
        $items = $this->buildItemList();
        $stats = $this->buildStats();

        return Inertia::render('packaging/Admin', compact('items', 'stats'));
    }

    // ── JSON API (polling) ────────────────────────────────────────────────────

    public function items()
    {
        return response()->json(['items' => $this->buildItemList()]);
    }

    public function scan(PackagingScanRequest $request)
    {
        $validated = $request->validated();

        $eanRecord = WorkOrderEan::where('ean', $validated['ean'])->first();

        if (! $eanRecord) {
            return response()->json(['message' => __('Unknown EAN')], 404);
        }

        $workOrder = WorkOrder::find($eanRecord->work_order_id);

        if (! $workOrder) {
            return response()->json(['message' => __('Work order not found')], 404);
        }

        if (! in_array($workOrder->status, [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS]) && $workOrder->produced_qty <= 0) {
            return response()->json([
                'message' => __('Work order not in a packable state (current: :status)', ['status' => $workOrder->status]),
            ], 422);
        }

        $planned = (int) $workOrder->planned_qty;
        if ($planned > 0 && $workOrder->packed_qty >= $planned) {
            return response()->json(['message' => __('Work order fully packed')], 422);
        }

        // Optional pallet assignment: the open pallet must belong to the same work
        // order as the scanned piece.
        $pallet = null;
        if (! empty($validated['pallet_id'])) {
            $pallet = Pallet::find($validated['pallet_id']);

            if (! $pallet || ! $pallet->isOpen()) {
                return response()->json(['message' => __('Pallet is not open')], 422);
            }

            if ($pallet->work_order_id !== $workOrder->id) {
                return response()->json(['message' => __('Piece does not belong to this pallet\'s work order')], 422);
            }
        }

        $workOrder->increment('packed_qty');
        \App\Sync\CollectionBroadcaster::flush($workOrder); // increment() bypasses model events
        $workOrder->refresh();

        if ($pallet) {
            $pallet->increment('qty');
            \App\Sync\CollectionBroadcaster::flush($pallet); // increment() bypasses model events
            $pallet->refresh()->loadMissing(['workOrder.line', 'batch']);
        }

        PackagingScanLog::create([
            'user_id' => $request->user()?->id,
            'work_order_id' => $workOrder->id,
            'pallet_id' => $pallet?->id,
            'ean' => $validated['ean'],
            'product_name' => $this->productLabel($workOrder),
            'scanned_at' => now(),
        ]);

        return response()->json([
            'work_order' => [
                'id' => $workOrder->id,
                'order_no' => $workOrder->order_no,
                'product' => $this->productLabel($workOrder),
                'planned_qty' => (int) $workOrder->planned_qty,
                'packed_qty' => $workOrder->packed_qty,
            ],
            'pallet' => $pallet ? $this->palletPayload($pallet) : null,
            'message' => __('Packed: :name', ['name' => $this->productLabel($workOrder)]),
        ]);
    }

    // ── Pallets (packing station) ───────────────────────────────────────────────

    public function openPallets(Request $request)
    {
        $query = Pallet::where('status', PalletStatus::Open->value)
            ->with(['workOrder:id,order_no,line_id', 'workOrder.line:id,name', 'batch:id,batch_number,lot_number'])
            ->orderByDesc('updated_at');

        if ($workOrderId = $request->integer('work_order_id')) {
            $query->where('work_order_id', $workOrderId);
        }

        // Filter by production line (derived from the pallet's work order) so the
        // station can show only the open pallets relevant to a given line.
        if ($lineId = $request->integer('line_id')) {
            $query->whereHas('workOrder', fn ($q) => $q->where('line_id', $lineId));
        }

        return response()->json([
            'pallets' => $query->limit(100)->get()->map(fn (Pallet $p) => $this->palletPayload($p)),
        ]);
    }

    public function createPallet(CreatePalletStationRequest $request)
    {
        $workOrder = WorkOrder::findOrFail($request->integer('work_order_id'));

        // Link the pallet to the batch it holds (one batch per pallet). Use the
        // explicit choice if given (and it belongs to the WO); otherwise auto-link
        // when the work order has exactly one batch.
        $batchId = $request->integer('batch_id') ?: null;
        if ($batchId) {
            if (! $workOrder->batches()->whereKey($batchId)->exists()) {
                return response()->json(['message' => __('Selected batch does not belong to this work order.')], 422);
            }
        } else {
            $batchIds = $workOrder->batches()->pluck('id');
            if ($batchIds->count() === 1) {
                $batchId = $batchIds->first();
            }
        }

        $pallet = Pallet::create([
            'work_order_id' => $workOrder->id,
            'batch_id' => $batchId,
            'status' => PalletStatus::Open->value,
            'location' => $request->input('location'),
            'qty' => 0,
        ]);

        return response()->json([
            'pallet' => $this->palletPayload($pallet->fresh(['workOrder.line', 'batch'])),
            'message' => __('Pallet :no created', ['no' => $pallet->pallet_no]),
        ], 201);
    }

    public function closePallet(Pallet $pallet)
    {
        if (! $pallet->isOpen()) {
            return response()->json(['message' => __('Pallet is not open')], 422);
        }

        $pallet->update(['status' => PalletStatus::Closed->value]);

        return response()->json([
            'pallet' => $this->palletPayload($pallet->fresh(['workOrder.line', 'batch'])),
            'message' => __('Pallet :no closed', ['no' => $pallet->pallet_no]),
        ]);
    }

    private function palletPayload(Pallet $pallet): array
    {
        return [
            'id' => $pallet->id,
            'pallet_no' => $pallet->pallet_no,
            'work_order_id' => $pallet->work_order_id,
            'order_no' => $pallet->workOrder?->order_no,
            'line_id' => $pallet->workOrder?->line_id,
            'line_name' => $pallet->workOrder?->line?->name,
            'batch_id' => $pallet->batch_id,
            'batch_lot' => $pallet->batch?->lot_number,
            'batch_number' => $pallet->batch?->batch_number,
            'qty' => (int) $pallet->qty,
            'status' => $pallet->status instanceof PalletStatus ? $pallet->status->value : $pallet->status,
            'location' => $pallet->location,
            'updated_at' => $pallet->updated_at?->toIso8601String(),
        ];
    }

    public function history()
    {
        $shiftStart = $this->currentShiftStart();

        $logs = PackagingScanLog::where('scanned_at', '>=', $shiftStart)
            ->orderByDesc('scanned_at')
            ->limit(50)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'ean' => $l->ean,
                'product_name' => $l->product_name,
                'scanned_at' => $l->scanned_at->format('H:i:s'),
                'after_id' => $l->id,
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
            ->map(fn ($l) => [
                'id' => $l->id,
                'ean' => $l->ean,
                'product_name' => $l->product_name,
                'scanned_at' => $l->scanned_at->format('H:i:s'),
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

        return WorkOrder::where(function ($q) {
                $q->whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])
                  ->orWhere('produced_qty', '>', 0);
            })
            ->with('productType', 'line', 'batches:id,work_order_id,batch_number,lot_number')
            ->orderByDesc('priority')
            ->get()
            ->filter(fn ($wo) => $eansByWorkOrder->has($wo->id))
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
                    // Batches the operator can assign a new pallet to (one per pallet).
                    'batches' => $wo->batches->map(fn ($b) => [
                        'id' => $b->id,
                        'label' => $b->displayLabel(),
                    ])->values(),
                    'status' => $wo->status,
                ];
            })
            ->values()
            ->toArray();
    }

    private function buildStats(): array
    {
        $shiftStart = $this->currentShiftStart();
        $todayPacked = PackagingScanLog::where('scanned_at', '>=', $shiftStart)->count();

        $plan = WorkOrder::where(function ($q) {
                $q->whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])
                  ->orWhere('produced_qty', '>', 0);
            })
            ->whereHas('eans')
            ->sum('planned_qty');

        $totalPacked = WorkOrder::where(function ($q) {
                $q->whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS])
                  ->orWhere('produced_qty', '>', 0);
            })
            ->whereHas('eans')
            ->sum('packed_qty');

        $backlog = max(0, (int) $plan - (int) $totalPacked);
        $shift = $this->currentShiftPayload();

        return [
            'today_packed' => $todayPacked,
            'plan' => (int) $plan,
            'total_packed' => (int) $totalPacked,
            'backlog' => $backlog,
            'shift_start' => $shiftStart->format('H:i'),
            'shift_name' => $shift['name'] ?? null,
            'shift_window' => $shift ? $shift['start'].'–'.$shift['end'] : null,
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

    /**
     * Start of the shift currently in progress — delegated to the shared
     * ShiftWindow helper so the station and the shift-handover balance agree.
     */
    private function currentShiftStart(): Carbon
    {
        return ShiftWindow::current()->start;
    }

    /**
     * Compact description of the active shift for the station header, or null
     * when none is configured (the UI then falls back to the fixed window).
     *
     * @return array{name: string, code: ?string, start: string, end: string}|null
     */
    private function currentShiftPayload(): ?array
    {
        return ShiftWindow::current()->shiftPayload();
    }
}
