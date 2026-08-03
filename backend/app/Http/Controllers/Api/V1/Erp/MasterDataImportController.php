<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\ImportBomsRequest;
use App\Http\Requests\Api\V1\Erp\ImportMaterialLotsRequest;
use App\Http\Requests\Api\V1\Erp\ImportMaterialsRequest;
use App\Http\Requests\Api\V1\Erp\ImportProductsRequest;
use App\Services\Erp\BomImportService;
use App\Services\Erp\MaterialImportService;
use App\Services\Erp\MaterialLotImportService;
use App\Services\Erp\ProductImportService;
use Illuminate\Http\JsonResponse;

/**
 * ERP → OpenMES master data (#212): products, materials, material lots and
 * recipes. One controller because all four share the same contract — a canonical
 * JSON list in, a per-row report out — and an ERP sync job typically calls them
 * in sequence.
 *
 * Every endpoint requires the `erp:masterdata:write` scope and is rate limited
 * via the `erp-import` limiter. Rows are independent: a row referencing something
 * OpenMES does not know is reported in `errors` while the rest of the batch is
 * applied, so the response is 207 Multi-Status rather than 200.
 */
class MasterDataImportController extends Controller
{
    public function products(ImportProductsRequest $request, ProductImportService $service): JsonResponse
    {
        $result = $service->import(
            $request->input('products'),
            $request->strategy(),
            $request->onlyCategories(),
            $request->input('external_system'),
        );

        return $this->report($result);
    }

    public function materials(ImportMaterialsRequest $request, MaterialImportService $service): JsonResponse
    {
        $result = $service->import(
            $request->input('materials'),
            $request->strategy(),
            $request->onlyCategories(),
            $request->input('external_system'),
        );

        return $this->report($result);
    }

    public function materialLots(ImportMaterialLotsRequest $request, MaterialLotImportService $service): JsonResponse
    {
        $result = $service->import(
            $request->input('lots'),
            $request->strategy(),
            $request->input('warehouse_code'),
        );

        return $this->report($result);
    }

    public function boms(ImportBomsRequest $request, BomImportService $service): JsonResponse
    {
        $result = $service->import($request->input('recipes'), $request->mode());

        return $this->report($result);
    }

    /**
     * 200 when every row applied cleanly, 207 when some rows failed but the batch
     * was processed. Malformed payloads never reach here — the form request
     * rejects them with 422.
     *
     * @param  array<string, mixed>  $result
     */
    private function report(array $result): JsonResponse
    {
        return response()->json(['data' => $result], $result['errors'] === [] ? 200 : 207);
    }
}
