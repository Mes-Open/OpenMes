<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignCartonPalletRequest;
use App\Http\Requests\OpenCartonRequest;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\UnitCarton;
use App\Models\WorkOrder;
use App\Services\Packaging\UnitPackingService;
use App\Services\Traceability\BindingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cartons of serialised units at the packing station: open, fill (via the PSN scan), close, put on a pallet. */
class UnitCartonController extends Controller
{
    public function __construct(private readonly UnitPackingService $packing) {}

    /** Open cartons, narrowed to a work order or a pallet when the station says which. */
    public function index(Request $request): JsonResponse
    {
        $cartons = UnitCarton::open()
            ->with(['workOrder:id,order_no', 'pallet:id,pallet_no', 'activeBy:id,name', 'activeWorkstation:id,name'])
            ->when($request->integer('work_order_id'), fn ($q, $id) => $q->where('work_order_id', $id))
            ->when($request->integer('pallet_id'), fn ($q, $id) => $q->where('pallet_id', $id))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['cartons' => $cartons->map(fn ($c) => $this->payload($c))]);
    }

    public function store(OpenCartonRequest $request): JsonResponse
    {
        $data = $request->validated();
        try {
            $carton = $this->packing->openCarton(
                $request->user(),
                isset($data['work_order_id']) ? WorkOrder::find($data['work_order_id']) : null,
                isset($data['pallet_id']) ? Pallet::find($data['pallet_id']) : null,
                ($request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id) ?: null,
            );
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'carton' => $this->payload($carton->load(['workOrder:id,order_no', 'pallet:id,pallet_no'])),
            'message' => __('Carton :no opened', ['no' => $carton->carton_no]),
        ], 201);
    }

    /** The carton with the units in it - for the station's list and the label. */
    public function show(UnitCarton $carton): JsonResponse
    {
        $carton->load(['workOrder:id,order_no', 'pallet:id,pallet_no']);

        return response()->json([
            'carton' => $this->payload($carton),
            'units' => $carton->units()->orderBy('id')->get(['id', 'serial_no', 'psn', 'status', 'packed_at'])
                ->map(fn (SerialUnit $u) => ['id' => $u->id, 'serial_no' => $u->serial_no, 'psn' => $u->psn, 'status' => $u->status, 'packed_at' => $u->packed_at?->toIso8601String()]),
        ]);
    }

    public function close(Request $request, UnitCarton $carton): JsonResponse
    {
        try {
            $carton = $this->packing->closeCarton($carton, $request->user());
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        // The box that fills the pallet closes the pallet too.
        $palletClosed = $this->packing->closePalletIfFull($carton->pallet) ? $carton->pallet->fresh() : null;

        return response()->json([
            'carton' => $this->payload($carton->load(['workOrder:id,order_no', 'pallet:id,pallet_no'])),
            'label_pdf' => $this->labelUrl($carton),
            'pallet_closed' => $palletClosed ? PackagingController::closedPalletPayload($palletClosed, $this->packing) : null,
            'message' => __('Carton :no closed with :count units', ['no' => $carton->carton_no, 'count' => $carton->qty]),
        ]);
    }

    /** Take a carton onto this bench (one per operator); closing or releasing lets it go. */
    public function activate(Request $request, UnitCarton $carton): JsonResponse
    {
        if (! $carton->isOpen()) {
            return response()->json(['message' => __('Carton :no is closed.', ['no' => $carton->carton_no])], 422);
        }
        $user = $request->user();
        UnitCarton::where('active_by_id', $user->id)->whereKeyNot($carton->id)->update(['active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
        $carton->update([
            'active_by_id' => $user->id,
            'active_workstation_id' => $request->session()->get('selected_workstation_id') ?: $user->workstation_id,
            'activated_at' => now(),
        ]);

        return response()->json(['carton' => $this->payload($carton->fresh(['workOrder:id,order_no', 'pallet:id,pallet_no', 'activeBy:id,name', 'activeWorkstation:id,name']))]);
    }

    public function release(Request $request, UnitCarton $carton): JsonResponse
    {
        if ($carton->active_by_id === $request->user()->id) {
            $carton->update(['active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
        }

        return response()->json(['carton' => $this->payload($carton->fresh(['workOrder:id,order_no', 'pallet:id,pallet_no']))]);
    }

    public function assignPallet(AssignCartonPalletRequest $request, UnitCarton $carton): JsonResponse
    {
        $pallet = Pallet::findOrFail($request->validated('pallet_id'));
        try {
            $carton = $this->packing->assignCartonToPallet($carton, $pallet, $request->user(), ($request->session()->get('selected_workstation_id') ?: $request->user()->workstation_id) ?: null);
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'carton' => $this->payload($carton->load(['workOrder:id,order_no', 'pallet:id,pallet_no'])),
            'message' => __('Carton :no put on pallet :pallet', ['no' => $carton->carton_no, 'pallet' => $pallet->pallet_no]),
        ]);
    }

    private function payload(UnitCarton $c): array
    {
        return [
            'id' => $c->id,
            'carton_no' => $c->carton_no,
            'status' => $c->status,
            'qty' => (int) $c->qty,
            'work_order_id' => $c->work_order_id,
            'order_no' => $c->workOrder?->order_no,
            'pallet_id' => $c->pallet_id,
            'pallet_no' => $c->pallet?->pallet_no,
            'closed_at' => $c->closed_at?->toIso8601String(),
            'active_by_id' => $c->active_by_id,
            'active_by' => $c->activeBy?->name,
            'active_workstation' => $c->activeWorkstation?->name,
            'label_pdf' => $this->labelUrl($c),
        ];
    }

    /** The carton label, on the template the order's packing step names when it does. */
    private function labelUrl(UnitCarton $c): string
    {
        return route('packaging.labels.carton.pdf', array_filter([
            'carton' => $c,
            'template' => $this->packing->labelTemplateIdFor($c->work_order_id, 'carton'),
        ]));
    }
}
