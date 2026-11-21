<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Shipment;
use App\Models\ShipmentScan;
use App\Services\Logistics\ShipmentLoadingException;
use App\Services\Logistics\ShipmentLoadingService;
use Illuminate\Http\JsonResponse;

/**
 * The JSON a scan / undo answers with — shared by the loading terminal (session)
 * and the REST API (Sanctum), so both get the same verdict and running totals.
 */
trait RespondsWithShipmentScans
{
    protected function scanResponse(ShipmentScan $scan, Shipment $shipment, ShipmentLoadingService $service): JsonResponse
    {
        // Three counts in one query: the terminal's live lists come from the
        // synced collections, the answer only needs the running totals.
        $totals = $service->totals($shipment);

        return response()->json(['data' => [
            'scan' => [
                'id' => $scan->id,
                'scanned_code' => $scan->scanned_code,
                'unit_type' => $scan->unit_type,
                'result' => $scan->result,
                'message' => $scan->message,
                'shipment_item_id' => $scan->shipment_item_id,
                'scanned_at' => $scan->scanned_at?->toIso8601String(),
            ],
            'totals' => $totals,
        ]]);
    }

    protected function refused(ShipmentLoadingException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}
