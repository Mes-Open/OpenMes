<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\BindUnitComponentRequest;
use App\Http\Requests\BindUnitLabelRequest;
use App\Http\Requests\BlockUnitRequest;
use App\Http\Requests\IssueUnitBatchRequest;
use App\Http\Requests\IssueUnitIdentifierRequest;
use App\Http\Requests\RegisterSubassemblyRequest;
use App\Http\Requests\ScrapUnitRequest;
use App\Http\Requests\StartUnitRequest;
use App\Http\Requests\UnbindUnitComponentRequest;
use App\Http\Requests\UnblockUnitRequest;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\Material;
use App\Models\ScrapReason;
use App\Models\SerialUnit;
use App\Models\SerialUnitComponent;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Lot\LotService;
use App\Services\Production\OperatorWorkstationSelection;
use App\Services\Traceability\BindingException;
use App\Services\Traceability\SerialTraceService;
use App\Support\UnitLabelActions;
use App\Support\UnitSerialisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UnitLabelStationController extends Controller
{
    public function __construct(
        private readonly SerialTraceService $serials,
    ) {}

    /**
     * Operator station screen for applying pre-printed serial labels to units.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        // The operator works on a selected line, and the layout shows which; no
        // line yet means the line picker first, as on every other operator screen.
        $lineId = app(OperatorWorkstationSelection::class)->resolveLine($request);
        if (! $lineId) {
            return redirect()->route('operator.select-line');
        }

        $line = Line::findOrFail($lineId);
        $selectedWorkstation = app(OperatorWorkstationSelection::class)->resolve($request, (int) $lineId);

        $orders = WorkOrder::whereIn('status', ['IN_PROGRESS', 'PENDING', 'ACCEPTED'])
            ->where('line_id', $line->id)
            ->with(['productType:id,name', 'bomTemplates:id'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $produced = self::producedSerialMaterials($orders);
        $workOrders = $orders
            ->map(fn (WorkOrder $wo) => [
                'id' => $wo->id,
                'order_no' => $wo->order_no,
                'product' => $wo->productType?->name ?? '',
                'status' => $wo->status,
                // Serial-tracked sub-assemblies this order makes: registered by their own SN here.
                'produces' => ($produced[$wo->id] ?? collect())
                    ->map(fn (Material $m) => ['id' => $m->id, 'code' => $m->code, 'name' => $m->name])->values(),
            ]);

        // Materials a component can be booked as - the tracked ones first, since
        // those are what gets scanned; a plant tracking nothing sees them all.
        $materials = Material::query()->where('is_active', true)
            ->orderByRaw("CASE WHEN tracking_type = 'none' THEN 1 ELSE 0 END")->orderBy('name')
            ->limit(300)->get(['id', 'code', 'name', 'tracking_type'])
            ->map(fn (Material $m) => ['id' => $m->id, 'code' => $m->code, 'name' => $m->name]);

        return Inertia::render('operator/unit-labels/Station', [
            'workOrders' => $workOrders,
            'materials' => $materials,
            // Error codes for holding a non-conforming unit: the plant's scrap reasons.
            'holdReasons' => ScrapReason::active()->ordered()->get(['id', 'code', 'name', 'category'])
                ->map(fn (ScrapReason $r) => ['id' => $r->id, 'code' => $r->code, 'name' => $r->name])->values(),
            'canUnblock' => $request->user()->hasAnyRole(['Supervisor', 'Admin']),
            // What this bench's operator does here (the first bench starts units, the labelling bench binds ...).
            'labelActions' => UnitLabelActions::for($request->user(), $selectedWorkstation),
            'line' => $line->only(['id', 'code', 'name']),
            'selectedWorkstation' => $selectedWorkstation?->only(['id', 'code', 'name']),
        ]);
    }

    /**
     * Bind a scanned serial number to its process serial number - the label
     * going onto the unit. The rules (formats, whether the process serial is
     * required or unique, who may re-bind) are the plant's settings; the
     * service applies them and explains a refusal.
     */
    public function apply(BindUnitLabelRequest $request): JsonResponse
    {
        if ($denied = $this->unlessAllowed($request, UnitLabelActions::LABEL)) {
            return $denied;
        }
        $data = $request->validated();
        $user = $request->user();

        // Re-binding a unit that already carries another process serial is a
        // supervisor's call; an operator's `force` is ignored, not trusted.
        $force = ! empty($data['force']) && $user->hasRole(['Supervisor', 'Admin']);

        try {
            $bound = $this->serials->bindProcessSerial($data['serial_no'], $data['psn'] ?? null, $user, [
                'work_order_id' => $data['work_order_id'] ?? null,
                'workstation_id' => $request->session()->get('selected_workstation_id') ?: $user->workstation_id,
                'force' => $force,
                'reason' => $data['reason'] ?? null,
            ]);
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage(), 'rebindable' => $e->rebindable], 422);
        }

        return response()->json([
            'created' => $bound['created'],
            'rebound' => $bound['rebound'],
            'unit' => $this->payload($bound['unit']),
            'message' => match (true) {
                $bound['serial_assigned'] ?? false => __('Serial number :sn assigned to unit :psn.', ['sn' => $bound['unit']->serial_no, 'psn' => $bound['unit']->psn]),
                $bound['created'] => __('Unit registered, process serial bound to serial number.'),
                $bound['rebound'] => __('Process serial re-bound.'),
                default => __('Unit already registered, label binding confirmed.'),
            },
        ]);
    }

    /**
     * Issue the next process or unit serial from the sequence configured for
     * the work order's product (Admin -> LOT sequences, "Numbers"). This is the
     * "create and print the process serial" step for lines that do not receive
     * pre-printed labels; the number comes back to the field the way a scan
     * would, and is validated the same way when the binding is applied.
     */
    public function issue(IssueUnitIdentifierRequest $request, LotService $lots): JsonResponse
    {
        if ($denied = $this->unlessAllowed($request, UnitLabelActions::ISSUE)) {
            return $denied;
        }
        $data = $request->validated();
        $workOrder = isset($data['work_order_id']) ? WorkOrder::find($data['work_order_id']) : null;
        $productType = $workOrder?->productType;

        try {
            $identifier = $lots->generate($productType, $data['purpose']);
        } catch (\RuntimeException) {
            $kind = $data['purpose'] === LotSequence::PURPOSE_PROCESS_SERIAL ? __('process serial') : __('unit serial');

            return response()->json([
                // Without a work order there is no product, so only a global
                // sequence could answer - say which of the two is missing.
                'message' => match (true) {
                    $productType !== null => __('Product :product has no :kind sequence. Add one under LOT sequences ("Numbers").', ['product' => $productType->name, 'kind' => $kind]),
                    $workOrder !== null => __('Order :order has no product type, so no :kind sequence applies. Give the order a product, or add a sequence without a product type to serve every order.', ['order' => $workOrder->order_no, 'kind' => $kind]),
                    default => __('Select a work order first - the :kind sequence belongs to its product. Or add a sequence without a product type to serve every order.', ['kind' => $kind]),
                },
            ], 422);
        }

        return response()->json(['identifier' => $identifier, 'purpose' => $data['purpose']]);
    }

    /**
     * Start a unit on its process serial - the line's first station, before
     * the product label exists. The PSN comes from a scanned label or, left
     * empty, from the product's sequence; the unit is registered on the order
     * and its PSN label is ready to print.
     */
    public function start(StartUnitRequest $request, LotService $lots): JsonResponse
    {
        if ($denied = $this->unlessAllowed($request, UnitLabelActions::START)) {
            return $denied;
        }
        $data = $request->validated();
        $workOrder = isset($data['work_order_id']) ? WorkOrder::find($data['work_order_id']) : null;
        $psn = $data['psn'] ?? null;
        if ($psn === null) {
            try {
                $psn = $lots->generate($workOrder?->productType, LotSequence::PURPOSE_PROCESS_SERIAL);
            } catch (\RuntimeException) {
                return response()->json(['message' => __('Product :product has no :kind sequence. Add one under LOT sequences ("Numbers").', ['product' => $workOrder?->productType?->name ?? $workOrder?->order_no, 'kind' => __('process serial')])], 422);
            }
        }

        try {
            $unit = $this->serials->startUnit($psn, $request->user(), [
                'work_order_id' => $workOrder?->id,
                'workstation_id' => $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id,
            ]);
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'unit' => $this->payload($unit->load('workOrder:id,order_no')),
            'label_pdf' => route('packaging.labels.serial-unit.pdf', $unit),
            'label_zpl' => route('packaging.labels.serial-unit.zpl', $unit),
            'message' => __('Unit :psn started - print its label.', ['psn' => $unit->psn]),
        ], 201);
    }

    /**
     * Register a sub-assembly the order makes by its own serial number, so the
     * product it goes into is later linked to it (and its history).
     */
    public function subassembly(RegisterSubassemblyRequest $request): JsonResponse
    {
        if ($denied = $this->unlessAllowed($request, UnitLabelActions::SUBASSEMBLY)) {
            return $denied;
        }
        $workOrder = WorkOrder::findOrFail($request->integer('work_order_id'));
        $material = Material::findOrFail($request->integer('material_id'));
        if (! (self::producedSerialMaterials(new \Illuminate\Database\Eloquent\Collection([$workOrder]))[$workOrder->id] ?? collect())->contains('id', $material->id)) {
            return response()->json(['message' => __('Order :order does not make :material.', ['order' => $workOrder->order_no, 'material' => $material->name])], 422);
        }

        try {
            $unit = $this->serials->registerSubassembly(
                $request->validated('serial_no'), $material, $workOrder, $request->user(),
                $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id,
            );
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'unit' => $this->payload($unit->load('workOrder:id,order_no')),
            'message' => __('Sub-assembly :sn registered as :material.', ['sn' => $unit->serial_no, 'material' => $material->name]),
        ], 201);
    }

    /**
     * The serial-tracked materials each order makes: those whose producing
     * routing is one the order runs (its BOM selection, else its product's
     * active routings). One query for the lot.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, WorkOrder>  $orders
     * @return array<int, \Illuminate\Support\Collection<int, Material>> order id => materials
     */
    private static function producedSerialMaterials(\Illuminate\Database\Eloquent\Collection $orders): array
    {
        $orders->loadMissing('bomTemplates:id');
        $fallback = \App\Models\ProcessTemplate::whereIn('product_type_id', $orders->filter(fn ($wo) => $wo->bomTemplates->isEmpty())->pluck('product_type_id')->filter()->unique())
            ->where('is_active', true)->get(['id', 'product_type_id'])->groupBy('product_type_id');
        $templatesOf = $orders->mapWithKeys(fn (WorkOrder $wo) => [$wo->id => $wo->bomTemplates->isNotEmpty()
            ? $wo->bomTemplates->pluck('id')
            : ($fallback[$wo->product_type_id] ?? collect())->pluck('id')]);

        $materials = Material::query()
            ->whereIn('producing_process_template_id', $templatesOf->flatten()->unique()->values())
            ->where('tracking_type', 'serial')->where('is_active', true)
            ->orderBy('name')->get(['id', 'code', 'name', 'producing_process_template_id']);

        return $templatesOf->map(fn ($ids) => $materials->whereIn('producing_process_template_id', $ids->all())->values())->all();
    }

    /** Hold a non-conforming unit with an error code; a supervisor releases it. */
    public function block(BlockUnitRequest $request, SerialUnit $serialUnit): JsonResponse
    {
        try {
            $unit = $this->serials->blockUnit(
                $serialUnit,
                $request->user(),
                ScrapReason::findOrFail($request->validated('scrap_reason_id')),
                $request->validated('note'),
                $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id,
            );
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['unit' => $this->payload($unit), 'message' => __('Unit :sn blocked.', ['sn' => $unit->display_id])]);
    }

    /** Release a held unit - supervisors and admins, with the reason. */
    public function unblock(UnblockUnitRequest $request, SerialUnit $serialUnit): JsonResponse
    {
        try {
            $unit = $this->serials->unblockUnit(
                $serialUnit,
                $request->user(),
                $request->validated('note'),
                $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id,
            );
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['unit' => $this->payload($unit), 'message' => __('Unit :sn released.', ['sn' => $unit->display_id])]);
    }

    /** Scrap a unit from the station - with a reason, recorded in its history. */
    public function scrap(ScrapUnitRequest $request, SerialUnit $serialUnit): JsonResponse
    {
        try {
            $unit = $this->serials->scrapUnit(
                $serialUnit,
                $request->user(),
                $request->validated('reason'),
                $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id,
            );
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['unit' => $this->payload($unit), 'message' => __('Unit :sn scrapped.', ['sn' => $unit->display_id])]);
    }

    /**
     * Scan a component onto a unit: the parent's serial, then the component's
     * own label. What the component turns out to be (a sub-assembly serial, a
     * lot, a bare vendor serial) is the service's job to work out.
     */
    public function component(BindUnitComponentRequest $request): JsonResponse
    {
        if ($denied = $this->unlessAllowed($request, UnitLabelActions::COMPONENTS)) {
            return $denied;
        }
        $data = $request->validated();
        // The unit by its SN, or by its PSN while the SN is not on it yet.
        $unit = SerialUnit::findByIdentifier($data['serial_no']);
        if (! $unit) {
            return response()->json(['message' => __('Unknown unit :sn - start it or apply its label first.', ['sn' => $data['serial_no']])], 404);
        }

        try {
            $component = $this->serials->bindComponent($unit, $data['identifier'], $request->user(), [
                'material_id' => $data['material_id'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'batch_step_id' => $data['batch_step_id'] ?? null,
                'workstation_id' => $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id,
            ]);
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'component' => $this->componentPayload($component->load('material:id,code,name')),
            'unit' => $this->payload($unit->fresh()),
            'message' => __('Component :id bound to unit :sn.', ['id' => $component->identifier, 'sn' => $unit->display_id]),
        ]);
    }

    /** Take a component out of a unit (a repair, a wrong scan) - the reason stays on the unit's history. */
    public function unbindComponent(UnbindUnitComponentRequest $request, SerialUnitComponent $component): JsonResponse
    {
        if ($denied = $this->unlessAllowed($request, UnitLabelActions::COMPONENTS)) {
            return $denied;
        }
        try {
            $component = $this->serials->unbindComponent($component, $request->user(), $request->validated('reason'),
                $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id);
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $unit = $component->unit;

        return response()->json([
            'unit' => $this->payload($unit),
            'components' => $unit->components()->installed()->with('material:id,code,name')->orderByDesc('bound_at')->get()->map(fn ($c) => $this->componentPayload($c)),
            'message' => __('Component :id removed from unit :sn.', ['id' => $component->identifier, 'sn' => $unit->display_id]),
        ]);
    }

    /**
     * Issue a batch of numbers ahead of production - the units are registered
     * on the order with their serial (and process serial, when the product has
     * that sequence) so the labels can be printed now and applied at the line
     * by scanning them.
     */
    public function issueBatch(IssueUnitBatchRequest $request, LotService $lots): JsonResponse
    {
        if ($denied = $this->unlessAllowed($request, UnitLabelActions::ISSUE)) {
            return $denied;
        }
        $data = $request->validated();
        $workOrder = WorkOrder::findOrFail($data['work_order_id']);
        $productType = $workOrder->productType;
        $settings = app(UnitSerialisation::class);

        // Without a unit-serial sequence there is nothing to print; a missing
        // process-serial sequence only matters where the plant requires PSNs.
        // PSN only: the units start on their process serial, the SN comes later
        // with the product label.
        $psnOnly = (bool) ($data['psn_only'] ?? false);
        $withPsn = $lots->previewNext($productType, LotSequence::PURPOSE_PROCESS_SERIAL) !== null;
        $missing = match (true) {
            $psnOnly && ! $withPsn => __('process serial'),
            ! $psnOnly && $lots->previewNext($productType, LotSequence::PURPOSE_UNIT_SERIAL) === null => __('unit serial'),
            ! $withPsn && $settings->get('unit_psn_required') => __('process serial'),
            default => null,
        };
        if ($missing !== null) {
            return response()->json(['message' => __('Product :product has no :kind sequence. Add one under LOT sequences ("Numbers").', ['product' => $productType?->name ?? $workOrder->order_no, 'kind' => $missing])], 422);
        }

        $workstationId = $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id;
        try {
            $units = DB::transaction(function () use ($data, $workOrder, $productType, $lots, $withPsn, $psnOnly, $request, $workstationId, $settings) {
                $made = [];
                for ($i = 0; $i < $data['quantity']; $i++) {
                    if ($psnOnly) {
                        $made[] = $this->serials->startUnit($lots->generate($productType, LotSequence::PURPOSE_PROCESS_SERIAL), $request->user(), [
                            'work_order_id' => $workOrder->id,
                            'workstation_id' => $workstationId,
                        ]);

                        continue;
                    }
                    // Stored the way a scan of the printed label will arrive (the
                    // pattern may space its groups for the eye; the scan drops them).
                    $serialNo = $settings->normalize($lots->generate($productType, LotSequence::PURPOSE_UNIT_SERIAL));
                    // A sequence that hands out a number already on a product
                    // (a reset counter, a pattern without a date) must not quietly
                    // re-label that product: the whole batch is refused.
                    if (SerialUnit::where('serial_no', $serialNo)->exists()) {
                        throw new BindingException(__('Serial :sn already exists - check the sequence before issuing more numbers.', ['sn' => $serialNo]));
                    }
                    $unit = $this->serials->registerUnit($serialNo, [
                        'psn' => $withPsn ? $settings->normalize($lots->generate($productType, LotSequence::PURPOSE_PROCESS_SERIAL)) : null,
                        'work_order_id' => $workOrder->id,
                    ]);
                    $this->serials->recordStep($unit, $request->user(), null, [
                        'workstation_id' => $workstationId,
                        'parameters' => array_filter(['event' => 'issued', 'psn' => $unit->psn, 'batch_of' => $data['quantity']]),
                    ]);
                    $made[] = $unit;
                }

                return $made;
            });
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $ids = implode(',', array_map(fn (SerialUnit $u) => $u->id, $units));

        return response()->json([
            'units' => array_map(fn (SerialUnit $u) => ['id' => $u->id, 'serial_no' => $u->serial_no, 'psn' => $u->psn], $units),
            'count' => count($units),
            'label_pdf' => route('packaging.labels.serial-units.pdf', ['ids' => $ids]),
            'label_zpl' => route('packaging.labels.serial-units.zpl', ['ids' => $ids]),
            'message' => __(':count numbers issued for :order - print the labels.', ['count' => count($units), 'order' => $workOrder->order_no]),
        ], 201);
    }

    /** Components currently in a unit, for the station's list. */
    public function components(Request $request): JsonResponse
    {
        $unit = SerialUnit::findByIdentifier(app(\App\Support\UnitSerialisation::class)->normalize($request->query('serial_no')));

        return response()->json([
            'unit' => $unit ? $this->payload($unit) : null,
            'components' => $unit
                ? $unit->components()->installed()->with('material:id,code,name')->orderByDesc('bound_at')->get()->map(fn ($c) => $this->componentPayload($c))
                : [],
        ]);
    }

    private function componentPayload(SerialUnitComponent $c): array
    {
        return [
            'id' => $c->id,
            'identifier' => $c->identifier,
            'material' => $c->material ? ['id' => $c->material->id, 'code' => $c->material->code, 'name' => $c->material->name] : null,
            'kind' => $c->component_serial_unit_id ? 'serial_unit' : ($c->material_lot_id ? 'material_lot' : 'identifier'),
            'quantity' => (float) $c->quantity,
            'bound_at' => $c->bound_at?->toIso8601String(),
        ];
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
            'display_id' => $unit->display_id,
            // Why the unit is held, when it is: the error code or the failed tests.
            'hold' => $unit->status === SerialUnit::STATUS_BLOCKED ? ($unit->extra_data['hold'] ?? ['source' => 'test']) : null,
            'status' => $unit->status,
            'work_order' => $unit->workOrder?->order_no,
            'applied_at' => $unit->extra_data['label_applied_at'] ?? null,
        ];
    }

    /**
     * The bench's label-station actions hold on the server too, not only in
     * the screen: a bench pinned to "components" cannot start units by a
     * hand-made request. Supervisors and admins keep every action.
     */
    private function unlessAllowed(Request $request, string $action): ?JsonResponse
    {
        $benchId = $request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id;
        if (in_array($action, UnitLabelActions::for($request->user(), $benchId ? Workstation::find($benchId) : null), true)) {
            return null;
        }

        return response()->json(['message' => __('This action is not available at this workstation.')], 403);
    }
}
