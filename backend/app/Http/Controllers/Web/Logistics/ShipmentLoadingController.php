<?php

namespace App\Http\Controllers\Web\Logistics;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Concerns\RespondsWithShipmentScans;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\ConfirmShipmentRequest;
use App\Http\Requests\Shipments\ScanShipmentRequest;
use App\Http\Requests\Shipments\UnloadShipmentItemRequest;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Services\Logistics\ShipmentLoadingException;
use App\Services\Logistics\ShipmentLoadingService;
use App\Support\Integration\ShipmentPresenter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The dock's loading terminal (#400): pick an open shipment, scan every label
 * with a keyboard-wedge scanner, undo a mistaken scan, confirm the load.
 *
 * The page never navigates while loading: scans, undos and the confirmation
 * are JSON calls, the answer is a toast, and the expected/loaded/missing list
 * updates itself from the synced collections — on this screen and on every
 * other screen watching the same shipment.
 */
class ShipmentLoadingController extends Controller
{
    use RespondsWithShipmentScans;

    public function __construct(private readonly ShipmentLoadingService $service) {}

    /** Picker of the shipments still on the dock. */
    public function index()
    {
        $this->authorize('viewAny', Shipment::class);

        return Inertia::render('logistics/LoadingPicker', [
            'statusLabels' => ShipmentStatus::labels(),
        ]);
    }

    public function show(Request $request, Shipment $shipment)
    {
        $this->authorize('view', $shipment);

        return Inertia::render('logistics/Loading', [
            'shipment' => ShipmentPresenter::header($shipment->load(ShipmentPresenter::RELATIONS)),
            'statusLabels' => ShipmentStatus::labels(),
            'canConfirmShort' => $request->user()->can('confirmShort', $shipment),
            'userNames' => User::query()->orderBy('id')->limit(2000)->pluck('name', 'id'),
        ]);
    }

    public function scan(ScanShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        try {
            $scan = $this->service->scan($shipment, $request->validated('code'), $request->user(), $this->workstationId($request));
        } catch (ShipmentLoadingException $e) {
            return $this->refused($e);
        }

        return $this->scanResponse($scan, $shipment, $this->service);
    }

    public function unload(UnloadShipmentItemRequest $request, Shipment $shipment, ShipmentItem $item): JsonResponse
    {
        try {
            $scan = $this->service->unload($item, $request->user(), $this->workstationId($request));
        } catch (ShipmentLoadingException $e) {
            return $this->refused($e);
        }

        return $this->scanResponse($scan, $shipment, $this->service);
    }

    public function confirm(ConfirmShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        try {
            $shipment = $this->service->confirm($shipment, $request->user(), $request->validated('short_reason'));
        } catch (ShipmentLoadingException $e) {
            return $this->refused($e);
        }

        return response()->json(['data' => ShipmentPresenter::present($shipment->fresh())]);
    }

    /** The bench this terminal is signed in at, when there is one. */
    private function workstationId(FormRequest $request): ?int
    {
        $id = $request->validated('workstation_id')
            ?? ($request->hasSession() ? $request->session()->get('selected_workstation_id') : null)
            ?? $request->user()->workstation_id;

        return $id ? (int) $id : null;
    }
}
