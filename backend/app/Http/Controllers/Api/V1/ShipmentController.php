<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Concerns\RespondsWithShipmentScans;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\ConfirmShipmentRequest;
use App\Http\Requests\Shipments\ScanShipmentRequest;
use App\Http\Requests\Shipments\ShipmentIndexRequest;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Requests\Shipments\UnloadShipmentItemRequest;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Logistics\ShipmentLoadingException;
use App\Services\Logistics\ShipmentLoadingService;
use App\Support\Integration\ShipmentPresenter;
use Illuminate\Http\JsonResponse;

/**
 * Scan-to-load verification over the REST API (#400): plan a load, scan labels
 * against it, undo a scan, confirm, and read the result (loaded with unit
 * counts, missing, unexpected, who and when). Same rules as the web terminal —
 * both go through ShipmentLoadingService.
 */
class ShipmentController extends Controller
{
    use RespondsWithShipmentScans;

    public function __construct(private readonly ShipmentLoadingService $service) {}

    public function index(ShipmentIndexRequest $request): JsonResponse
    {
        $shipments = Shipment::query()
            ->with(ShipmentPresenter::RELATIONS)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->boolean('active'), fn ($q) => $q->whereIn('status', ShipmentStatus::activeValues()))
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Shipment $s) => ShipmentPresenter::header($s));

        return response()->json(['data' => $shipments]);
    }

    public function show(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        return response()->json(['data' => ShipmentPresenter::present($shipment)]);
    }

    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $shipment = $this->service->create($request->shipmentAttributes(), $request->expectedItems(), $request->user());

        return response()->json(['data' => ShipmentPresenter::present($shipment->fresh())], 201);
    }

    public function scan(ScanShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        try {
            $scan = $this->service->scan($shipment, $request->validated('code'), $request->user(), $request->validated('workstation_id'));
        } catch (ShipmentLoadingException $e) {
            return $this->refused($e);
        }

        return $this->scanResponse($scan, $shipment, $this->service);
    }

    public function unload(UnloadShipmentItemRequest $request, Shipment $shipment, ShipmentItem $item): JsonResponse
    {
        try {
            $scan = $this->service->unload($item, $request->user(), $request->validated('workstation_id'));
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
}
