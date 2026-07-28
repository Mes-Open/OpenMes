<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\ImportWorkOrdersRequest;
use App\Services\CsvImport\WorkOrderImportService;
use Illuminate\Http\JsonResponse;

/**
 * ERP → OpenMES: bulk import of work orders. Accepts a canonical JSON payload
 * (see ImportWorkOrdersRequest); each order resolves its line and product type
 * by code. Well-formed payloads always return a per-order report — a bad
 * reference in one order does not fail the batch. Requires the
 * `erp:orders:import` scope; rate limited via the `erp-import` limiter.
 */
class WorkOrderImportController extends Controller
{
    public function store(ImportWorkOrdersRequest $request, WorkOrderImportService $service): JsonResponse
    {
        $result = $service->importErp($request->input('orders'), $request->strategy());

        // 207 Multi-Status when some rows failed but the batch was processed;
        // 200 when every order was applied cleanly. Malformed payloads never
        // reach here — the form request rejects them with 422.
        $status = $result['errors'] === [] ? 200 : 207;

        return response()->json(['data' => $result], $status);
    }
}
