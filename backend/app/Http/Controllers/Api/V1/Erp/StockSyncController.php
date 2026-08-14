<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\BuildsCursorMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\ImportStockRequest;
use App\Http\Requests\Api\V1\Erp\StockExportRequest;
use App\Models\WarehouseStock;
use App\Services\Erp\StockImportService;
use Illuminate\Http\JsonResponse;

/**
 * Warehouse balances, both directions (#212).
 *
 * ERP → OpenMES (`import`, scope `erp:stock:write`): a quantity snapshot per
 * (warehouse, item). The ERP owns the warehouse, so an imported quantity replaces
 * the OpenMES balance and re-running the sync converges.
 *
 * OpenMES → ERP (`index`, scope `erp:stock:read`): what OpenMES currently thinks
 * is on hand, for reconciliation.
 */
class StockSyncController extends Controller
{
    use BuildsCursorMeta;

    public function import(ImportStockRequest $request, StockImportService $service): JsonResponse
    {
        $result = $service->import($request->input('balances'), $request->input('warehouse_code'));

        return response()->json(['data' => $result], $result['errors'] === [] ? 200 : 207);
    }

    public function index(StockExportRequest $request): JsonResponse
    {
        $query = WarehouseStock::query()
            ->with(['warehouse:id,code,name,erp_code', 'material:id,code,name', 'productType:id,code,name', 'materialLot:id,lot_number'])
            ->orderBy('id');

        if ($since = $request->input('since')) {
            $query->where('updated_at', '>=', $since);
        }

        if ($warehouse = $request->input('warehouse')) {
            $query->whereHas('warehouse', fn ($q) => $q->where('code', $warehouse)->orWhere('erp_code', $warehouse));
        }

        $page = $query->cursorPaginate($request->perPage())->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (WarehouseStock $stock) => [
                'warehouse_code' => $stock->warehouse?->code,
                'warehouse_erp_code' => $stock->warehouse?->erp_code,
                'material_code' => $stock->material?->code,
                'product_type_code' => $stock->productType?->code,
                'lot_number' => $stock->materialLot?->lot_number,
                'quantity' => (float) $stock->quantity,
                'unit_of_measure' => $stock->unit_of_measure,
                'erp_synced_at' => $stock->erp_synced_at?->toIso8601String(),
                'updated_at' => $stock->updated_at?->toIso8601String(),
            ]),
            'meta' => $this->cursorMeta($page),
        ]);
    }
}
