<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\BuildsCursorMeta;
use App\Http\Controllers\Api\V1\Erp\Concerns\FiltersSince;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\ShipmentExportRequest;
use App\Http\Requests\Api\V1\Erp\StoreErpShipmentRequest;
use App\Models\Shipment;
use App\Services\Logistics\ShipmentConflictException;
use App\Services\Logistics\ShipmentLoadingException;
use App\Services\Logistics\ShipmentLoadingService;
use App\Support\Integration\ShipmentPresenter;
use Illuminate\Http\JsonResponse;

/**
 * ERP ↔ OpenMES shipments (#400).
 *
 * In (`erp:shipments:write`): the ERP sends a load with the pallets / cartons it
 * must carry. Re-sending the same shipment_no replaces it while it is still
 * planned; once the dock has started loading it is 409.
 *
 * Out (`erp:shipments:read`): the load result — loaded units with counts,
 * missing, unexpected scans, who confirmed when — one shipment by number, or a
 * cursor-paginated list changed `?since=`.
 *
 * Shipments are resolved in the controller, never by route-model binding: the
 * API-key middleware sets the tenant after bindings would have run (see
 * ProductionExportController::show()).
 */
class ShipmentController extends Controller
{
    use BuildsCursorMeta;
    use FiltersSince;

    public function __construct(private readonly ShipmentLoadingService $service) {}

    public function store(StoreErpShipmentRequest $request): JsonResponse
    {
        try {
            [$shipment, $created] = $this->service->createOrReplace($request->shipmentAttributes(), $request->expectedItems());
        } catch (ShipmentConflictException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (ShipmentLoadingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ShipmentPresenter::present($shipment->fresh())], $created ? 201 : 200);
    }

    public function show(string $shipmentNo): JsonResponse
    {
        $shipment = Shipment::where('shipment_no', $shipmentNo)->firstOrFail();

        return response()->json(['data' => ShipmentPresenter::present($shipment)]);
    }

    public function index(ShipmentExportRequest $request): JsonResponse
    {
        $query = Shipment::query()->with(ShipmentPresenter::RELATIONS)->orderBy('id');
        $this->applySince($query, $request);
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $page = $query->cursorPaginate($request->perPage())->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (Shipment $s) => ShipmentPresenter::present($s)),
            'meta' => $this->cursorMeta($page),
        ]);
    }
}
