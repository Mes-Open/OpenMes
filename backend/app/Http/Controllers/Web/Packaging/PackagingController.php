<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Enums\PalletStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePalletStationRequest;
use App\Http\Requests\PackagingScanRequest;
use App\Http\Requests\ScanUnitRequest;
use App\Http\Requests\StartPackingStepRequest;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\PackagingScanLog;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\TemplateStep;
use App\Models\UnitCarton;
use App\Models\WorkOrder;
use App\Models\WorkOrderEan;
use App\Services\Material\MaterialAllocationService;
use App\Services\Packaging\UnitPackingService;
use App\Services\Production\OperatorWorkstationSelection;
use App\Services\Production\PalletBackflushService;
use App\Services\Traceability\BindingException;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PackagingController extends Controller
{
    /** One packing service per request: its config cache then serves every pallet in a list. */
    private ?UnitPackingService $packingService = null;

    private function packing(): UnitPackingService
    {
        return $this->packingService ??= app(UnitPackingService::class);
    }

    /** The bench this operator picked for the session, else their account's bench. */
    private static function benchId(Request $request): ?int
    {
        return ($request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id) ?: null;
    }

    // ── Views ─────────────────────────────────────────────────────────────────

    public function station(Request $request)
    {
        // One screen, two addresses: operators live under /operator (with the
        // line and bench in the query), supervisors and admins under /packaging.
        if ($request->routeIs('packaging.station') && ! $request->user()->hasAnyRole(['Admin', 'Supervisor'])) {
            return redirect()->route('operator.packaging', $request->query());
        }

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

        // The operator layout names the line in its header and shows its nav
        // only with one; admins reach this page from the sidebar and need neither.
        $selection = app(OperatorWorkstationSelection::class);
        $lineId = $selection->resolveLine($request) ?: $request->user()->workstation?->line_id;
        $line = $lineId ? Line::find($lineId)?->only(['id', 'code', 'name']) : null;
        $selectedWorkstation = $lineId
            ? $selection->resolve($request, (int) $lineId, allowOtherLines: true)?->only(['id', 'code', 'name'])
            : null;

        $packingSteps = $this->packing()->openPackingSteps($selectedWorkstation['id'] ?? null);

        return Inertia::render('packaging/Station', compact('scannerMode', 'labelTemplates', 'currentShift', 'line', 'selectedWorkstation', 'packingSteps'));
    }

    public function adminOverview()
    {
        $items = $this->buildItemList();
        $stats = $this->buildStats();

        return Inertia::render('packaging/Admin', compact('items', 'stats'));
    }

    // ── JSON API (polling) ────────────────────────────────────────────────────

    /** The packing steps this station works on (polled with the rest of the station's data). */
    public function packingSteps(Request $request, UnitPackingService $packing)
    {
        $workstationId = $request->session()->get('selected_workstation_id') ?: null;

        return response()->json(['steps' => $packing->openPackingSteps($workstationId)]);
    }

    public function items()
    {
        return response()->json([
            'items' => $this->buildItemList(),
            // Every packable order, EAN or not: serialised goods are packed by
            // process serial, so their orders never carry an EAN yet still need a pallet.
            'pallet_orders' => WorkOrder::packable()
                ->with('productType', 'batches:id,work_order_id,batch_number,lot_number')
                ->orderByDesc('priority')->orderByDesc('id')
                ->get()
                ->map(fn ($wo) => [
                    'id' => $wo->id,
                    'order_no' => $wo->order_no,
                    'product' => $this->productLabel($wo),
                    'batches' => $wo->batches->map(fn ($b) => ['id' => $b->id, 'label' => $b->displayLabel()])->values(),
                ])->values(),
        ]);
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

        if (! WorkOrder::whereKey($workOrder->id)->packable()->exists()) {
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

    /**
     * Carton label by process serial number: the operator scans the unit's
     * process serial label and gets the unit's serial number back with
     * ready-to-print label URLs.
     */
    public function scanUnit(ScanUnitRequest $request, UnitPackingService $packing)
    {
        // The label carries the process serial, the unit serial, or both - a unit
        // with no process serial (the product has no PSN sequence) is packed by
        // its serial number. Newest first: with unique process serials there is
        // one; without, the most recently bound unit is the one on the bench.
        $scanned = $request->validated('psn');
        $identifier = app(\App\Support\UnitSerialisation::class)->normalize($scanned) ?? $scanned;
        $unit = SerialUnit::where(fn ($q) => $q->whereIn('psn', [$scanned, $identifier])->orWhereIn('serial_no', [$scanned, $identifier]))
            ->orderByDesc('id')->first();

        if (! $unit) {
            return response()->json(['message' => __('Unknown process serial or serial number')], 404);
        }

        // Packaging comes from the BOM: with lot tracking on, the carton's lot is
        // picked before the first unit goes in, so the box is traceable too.
        $packingStep = $packing->packingStepFor($unit->work_order_id, $unit->batch_id);
        if ($packingStep && ($gate = $packing->packingMaterialsGate($packingStep))) {
            return response()->json(['message' => $gate, 'needs_picks' => true, 'packing_step_id' => $packingStep->id], 422);
        }

        // A unit the line took off is refused before the bench does anything with it.
        try {
            $packing->assertPackable($unit);
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Weight check (the packing step's expected weight and tolerance): the
        // unit is weighed before it goes into the box. Without a weight the
        // station asks for one; outside the tolerance the scan is refused and
        // the unit stays on the bench, the reading kept on its history.
        $config = $packing->packingConfigFor($unit->work_order_id);
        $measured = [];
        // A unit already in its box is not weighed again: a rescan changes nothing.
        $alreadyPlaced = $unit->packed_at !== null && ($unit->carton_id || $unit->pallet_id);
        if (isset($config['weight_expected_g']) && ! $alreadyPlaced) {
            $expected = (float) $config['weight_expected_g'];
            $tolerance = (float) ($config['weight_tolerance_g'] ?? 0);
            $weight = $request->validated('weight_g');
            $range = ['expected' => rtrim(rtrim(number_format($expected, 2, '.', ''), '0'), '.'), 'tolerance' => rtrim(rtrim(number_format($tolerance, 2, '.', ''), '0'), '.')];
            if ($weight === null) {
                return response()->json([
                    'message' => __('Weigh unit :sn: :expected g ± :tolerance g.', ['sn' => $unit->display_id] + $range),
                    'needs_weight' => true, 'expected_g' => $expected, 'tolerance_g' => $tolerance,
                ], 422);
            }
            $weight = round((float) $weight, 2);
            if (abs($weight - $expected) > $tolerance + 1e-9) {
                app(\App\Services\Traceability\SerialTraceService::class)->recordStep($unit, $request->user(), null, [
                    'workstation_id' => self::benchId($request),
                    'parameters' => ['event' => 'weight_check', 'verdict' => 'fail', 'weight_g' => $weight, 'expected_g' => $expected, 'tolerance_g' => $tolerance],
                ]);

                return response()->json([
                    'message' => __('Weight :weight g is outside :expected g ± :tolerance g - check unit :sn.', ['weight' => $weight, 'sn' => $unit->display_id] + $range),
                    'needs_weight' => true, 'weight_out_of_tolerance' => true, 'expected_g' => $expected, 'tolerance_g' => $tolerance,
                ], 422);
            }
            $measured = ['weight_g' => $weight];
        }

        $carton = $request->validated('carton_id') ? UnitCarton::find($request->validated('carton_id')) : null;
        $pallet = $request->validated('pallet_id') ? Pallet::find($request->validated('pallet_id')) : null;
        $auto = $request->boolean('auto_carton');
        // On "auto" the bench's box and pallet follow the unit: a box or pallet
        // left open for another order is not where this unit goes, so it is set
        // aside rather than refusing the scan. A box picked by hand still is checked.
        $otherOrder = fn ($container) => $container && $unit->work_order_id && $container->work_order_id && $container->work_order_id !== $unit->work_order_id;
        // A full box is set aside the same way: the next unit starts a new one.
        $capacity = $packing->cartonCapacityFor($unit->work_order_id);
        if ($auto && ($otherOrder($carton) || ($carton && $capacity && $carton->qty >= $capacity && $unit->carton_id !== $carton->id))) {
            $carton = null;
        }
        if ($auto && $otherOrder($pallet)) {
            $pallet = null;
        }
        // On "auto" a unit that is already packed stays where it is: the bench's
        // box is only a guess, and aiming the rescan at it would refuse the unit
        // (or open an empty carton) instead of saying it is already packed.
        if ($auto && $alreadyPlaced) {
            $carton = $pallet = null;
        }
        // Whether to print the unit's label here: by default every scan prints
        // one (a box label read off the unit's serial). A packing step can turn
        // that off, and then only a unit that has no label yet gets one.
        $labelNeeded = ($config['unit_label'] ?? true) !== false || empty($unit->extra_data['label_applied_at']);

        // The service refuses a unit the line has taken off (scrapped, blocked
        // after tests, already shipped) - that is how a bad unit leaves.
        // A second read of a packed unit changes nothing; the bench says so rather
        // than confirm it as if the unit had just gone in.
        $placedBefore = $unit->packed_at !== null ? [$unit->carton_id, $unit->pallet_id] : null;
        // The box opened for this scan and the packing stand or fall together: a
        // refused unit leaves no empty carton behind on the bench.
        try {
            [$unit, $carton] = DB::transaction(function () use ($request, $packing, $unit, $carton, $pallet, $auto, $alreadyPlaced, $config, $capacity, $measured) {
                // The bench had no box open (or only one for another order): when the
                // order packs into cartons, the scan takes the operator's open carton of
                // this order again, else opens one, so nobody has to remember "New
                // carton" between boxes or orders and no unit lands loose by mistake.
                if (! $carton && $auto && ! $alreadyPlaced && $config !== [] && ($config['unit'] ?? 'carton') === 'carton') {
                    $user = $request->user();
                    // Mine: on my bench, or opened by me and left on nobody's - never the
                    // box another packer has taken over, and never a full one.
                    $carton = UnitCarton::where('status', UnitCarton::STATUS_OPEN)->where('work_order_id', $unit->work_order_id)
                        ->where(fn ($q) => $q->where('active_by_id', $user->id)->orWhere(fn ($q2) => $q2->where('created_by_id', $user->id)->whereNull('active_by_id')))
                        ->when($capacity, fn ($q) => $q->where('qty', '<', $capacity))
                        ->latest('id')->first();
                    if ($carton && (int) $carton->active_by_id !== (int) $user->id) {
                        // Back to this order's box: it becomes the bench's box again.
                        UnitCarton::where('active_by_id', $user->id)->update(['active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
                        $carton->update(['active_by_id' => $user->id, 'active_workstation_id' => self::benchId($request), 'activated_at' => now()]);
                    }
                    $carton ??= $packing->openCarton($user, $unit->workOrder, $pallet, self::benchId($request));
                }

                return [$packing->packUnit($unit, $request->user(), $carton, $pallet, self::benchId($request), $measured), $carton];
            });
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $unit->loadMissing(['workOrder.productType', 'carton:id,carton_no,qty,status', 'pallet']);
        $wo = $unit->workOrder;
        $palletClosed = $packing->closePalletIfFull($unit->pallet) ? $unit->pallet->fresh() : null;
        $template = $packing->labelTemplateIdFor($unit->work_order_id, 'unit');
        $alreadyPacked = $placedBefore === [$unit->carton_id, $unit->pallet_id];

        return response()->json([
            'unit' => [
                'id' => $unit->id,
                'serial_no' => $unit->serial_no,
                'psn' => $unit->psn,
                'status' => $unit->status,
                'work_order_id' => $unit->work_order_id,
                'work_order' => $wo ? [
                    'order_no' => $wo->order_no,
                    'product' => $this->productLabel($wo),
                ] : null,
                'carton' => $unit->carton ? ['id' => $unit->carton->id, 'carton_no' => $unit->carton->carton_no, 'qty' => (int) $unit->carton->qty] : null,
                'pallet_no' => $unit->pallet?->pallet_no,
                'packed_at' => $unit->packed_at?->format('H:i'),
            ],
            'already_packed' => $alreadyPacked,
            // The box reached the packing step's size: the station closes it and prints its list.
            'carton_full' => (bool) ($capacity && $unit->carton?->isOpen() && $unit->carton->qty >= $capacity),
            'label_pdf' => route('packaging.labels.serial-unit.pdf', array_filter(['serialUnit' => $unit, 'template' => $template])),
            'label_zpl' => route('packaging.labels.serial-unit.zpl', array_filter(['serialUnit' => $unit, 'template' => $template])),
            'label_needed' => $labelNeeded,
            'carton_opened' => $carton && $carton->wasRecentlyCreated ? $carton->carton_no : null,
            'pallet_closed' => $palletClosed ? $this->closedPalletPayload($palletClosed, $packing) : null,
            'message' => $alreadyPacked
                ? __('Unit :sn is already packed - nothing changed.', ['sn' => $unit->display_id])
                : __('Unit :sn found - print the carton label.', ['sn' => $unit->display_id]),
        ]);
    }

    // ── Pallets (packing station) ───────────────────────────────────────────────

    public function openPallets(Request $request)
    {
        $query = Pallet::where('status', PalletStatus::Open->value)
            ->with(['workOrder:id,order_no,line_id', 'workOrder.line:id,name', 'batch:id,batch_number,lot_number', 'activeBy:id,name', 'activeWorkstation:id,name'])
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

    public function createPallet(CreatePalletStationRequest $request, PalletBackflushService $backflush)
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

        Pallet::where('active_by_id', $request->user()->id)->update(['active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
        $pallet = Pallet::create([
            'work_order_id' => $workOrder->id,
            'batch_id' => $batchId,
            'status' => PalletStatus::Open->value,
            'location' => $request->input('location'),
            'qty' => 0,
            'active_by_id' => $request->user()->id,
            'active_workstation_id' => $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id,
            'activated_at' => now(),
        ]);

        // Milestone backflush: when enabled, declare the BOM consumption implied
        // by the produced quantity and deduct it from stock, linked to the pallet.
        // Without an explicit produced_qty the batch is backflushed once (at its
        // first pallet), so splitting a batch across pallets doesn't double-book.
        if ($backflush->isEnabled()) {
            $explicitQty = $request->filled('produced_qty') ? (float) $request->input('produced_qty') : null;
            $backflush->backflushForPallet($pallet, $explicitQty, $request->user());
        }

        return response()->json([
            'pallet' => $this->palletPayload($pallet->fresh(['workOrder.line', 'batch', 'activeBy', 'activeWorkstation'])),
            'message' => __('Pallet :no created', ['no' => $pallet->pallet_no]),
        ], 201);
    }

    public function closePallet(Pallet $pallet)
    {
        // Only the request that actually closes it books the pallet's packaging:
        // a double click or a second station must not close it twice.
        $closed = Pallet::whereKey($pallet->id)->where('status', PalletStatus::Open->value)
            ->update(['status' => PalletStatus::Closed->value, 'active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
        if ($closed === 0) {
            return response()->json(['message' => __('Pallet is not open')], 422);
        }
        $pallet->refresh();
        $this->packing()->palletClosed($pallet);

        return response()->json([
            'pallet' => $this->palletPayload($pallet->fresh(['workOrder.line', 'batch'])),
            'message' => __('Pallet :no closed', ['no' => $pallet->pallet_no]),
        ]);
    }

    /**
     * Take a pallet onto this bench. One pallet per operator: whatever they had
     * before is released, and taking over someone else's is allowed but visible
     * (the list names who had it) - a shift change, not a mystery.
     */
    public function activatePallet(Request $request, Pallet $pallet)
    {
        if (! $pallet->isOpen()) {
            return response()->json(['message' => __('Pallet is not open')], 422);
        }
        $user = $request->user();
        Pallet::where('active_by_id', $user->id)->whereKeyNot($pallet->id)->update(['active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
        $pallet->update([
            'active_by_id' => $user->id,
            'active_workstation_id' => $request->session()->get('selected_workstation_id') ?: $user->workstation_id,
            'activated_at' => now(),
        ]);

        return response()->json(['pallet' => $this->palletPayload($pallet->fresh(['workOrder.line', 'batch', 'activeBy', 'activeWorkstation']))]);
    }

    /** Put the pallet down without closing it - it stays open for anyone. */
    public function releasePallet(Request $request, Pallet $pallet)
    {
        if ($pallet->active_by_id === $request->user()->id) {
            $pallet->update(['active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
        }

        return response()->json(['pallet' => $this->palletPayload($pallet->fresh(['workOrder.line', 'batch']))]);
    }

    /** The packaging a packing step takes from the BOM, with the lots to pick from when lot tracking is on. */
    public function packingStepMaterials(BatchStep $batchStep, UnitPackingService $packing, MaterialAllocationService $allocations)
    {
        abort_unless($batchStep->kind === TemplateStep::KIND_PACKING, 404);

        return response()->json([
            'materials' => $packing->packingMaterials($batchStep),
            'candidates' => $batchStep->status === BatchStep::STATUS_READY ? $allocations->pickPreviewForStep($batchStep) : [],
        ]);
    }

    /** Start the packing step from the station with the picked packaging lots. */
    public function startPackingStep(StartPackingStepRequest $request, BatchStep $batchStep, UnitPackingService $packing)
    {
        abort_unless($batchStep->kind === TemplateStep::KIND_PACKING, 404);
        try {
            $step = $packing->startPackingStep($batchStep, $request->user(), $request->validated('picks') ?? []);
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'materials' => $packing->packingMaterials($step),
            'message' => __('Packing step :name started - packaging reserved.', ['name' => $step->name]),
        ]);
    }

    /** A pallet that just closed itself: what the station needs to print it and say so. */
    public static function closedPalletPayload(Pallet $pallet, UnitPackingService $packing): array
    {
        $template = $packing->labelTemplateIdFor($pallet->work_order_id, 'pallet');

        return [
            'id' => $pallet->id,
            'pallet_no' => $pallet->pallet_no,
            'qty' => (int) $pallet->qty,
            'label_pdf' => route('packaging.labels.pallet.pdf', array_filter(['pallet' => $pallet, 'template' => $template])),
            'label_zpl' => route('packaging.labels.pallet.zpl', array_filter(['pallet' => $pallet, 'template' => $template])),
            'message' => __('Pallet :no is full and was closed', ['no' => $pallet->pallet_no]),
        ];
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
            'active_by_id' => $pallet->active_by_id,
            'active_by' => $pallet->activeBy?->name,
            'active_workstation' => $pallet->activeWorkstation?->name,
            'updated_at' => $pallet->updated_at?->toIso8601String(),
            // The packing step may name the pallet label to print; the station's print button prefers it.
            'label_template_id' => $this->packing()->labelTemplateIdFor($pallet->work_order_id, 'pallet'),
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

        return WorkOrder::packable()
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
        // Pieces packed this shift: EAN scans plus serialised units packed by process serial.
        $todayPacked = PackagingScanLog::where('scanned_at', '>=', $shiftStart)->count()
            + SerialUnit::where('packed_at', '>=', $shiftStart)->count();

        // Orders on this station: the EAN-scanned ones and the serialised ones.
        $eanOrders = WorkOrder::packable()->whereHas('eans');
        $serialOrders = WorkOrder::packable()->whereDoesntHave('eans')->whereHas('serialUnits');

        $plan = (clone $eanOrders)->sum('planned_qty') + (clone $serialOrders)->sum('planned_qty');
        $totalPacked = (clone $eanOrders)->sum('packed_qty')
            + SerialUnit::whereNotNull('packed_at')->whereIn('work_order_id', (clone $serialOrders)->select('id'))->count();

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
