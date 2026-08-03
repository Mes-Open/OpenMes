<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\BuildsCursorMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\AcknowledgeStockDocumentRequest;
use App\Http\Requests\Api\V1\Erp\StockExportRequest;
use App\Models\StockDocument;
use App\Services\Warehouse\StockDocumentService;
use Illuminate\Http\JsonResponse;

/**
 * OpenMES → ERP: warehouse documents production generated (#212).
 *
 * This is the "create the release / receipt document in the ERP" half of the
 * integration: the ERP polls for material releases and product receipts it has
 * not booked yet (`unsynced_only=1`), books its own counterpart, then calls
 * `acknowledge` with its document number so the entry leaves the backlog.
 *
 * Reading requires `erp:stock:read`, acknowledging `erp:stock:write`; both are
 * rate limited via the `erp-read` / `erp-import` limiters.
 */
class StockDocumentExportController extends Controller
{
    use BuildsCursorMeta;

    public function index(StockExportRequest $request): JsonResponse
    {
        $query = StockDocument::query()
            ->with([
                'warehouse:id,code,name,erp_code',
                'workOrder:id,order_no',
                'lines.material:id,code,name',
                'lines.productType:id,code,name',
                'lines.materialLot:id,lot_number',
            ])
            ->orderBy('id');

        if ($since = $request->input('since')) {
            $query->where('updated_at', '>=', $since);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Default to posted documents: a draft is not yet a real stock movement,
        // so booking it in the ERP would be premature.
        $query->where('status', $request->input('status', StockDocument::STATUS_POSTED));

        if ($warehouse = $request->input('warehouse')) {
            $query->whereHas('warehouse', fn ($q) => $q->where('code', $warehouse)->orWhere('erp_code', $warehouse));
        }

        if ($request->boolean('unsynced_only')) {
            $query->notSynced();
        }

        $page = $query->cursorPaginate($request->perPage())->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (StockDocument $doc) => $this->present($doc)),
            'meta' => $this->cursorMeta($page),
        ]);
    }

    /**
     * Record the ERP's own document number against ours.
     *
     * The id is resolved here instead of via route-model binding on purpose:
     * SubstituteBindings runs before this route's API-key middleware, so a bound
     * model would be looked up before TenantContext is set and could reach another
     * tenant's document. See ProductionExportController::show().
     */
    public function acknowledge(
        AcknowledgeStockDocumentRequest $request,
        int $stockDocument,
        StockDocumentService $service,
    ): JsonResponse {
        $document = StockDocument::findOrFail($stockDocument);

        $service->acknowledge($document, $request->input('erp_reference'));

        // present() reads each line's material / product / lot, so load them here
        // rather than paying a query per line.
        return response()->json(['data' => $this->present($document->fresh([
            'warehouse', 'workOrder', 'lines.material', 'lines.productType', 'lines.materialLot',
        ]))]);
    }

    private function present(StockDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'document_no' => $doc->document_no,
            'type' => $doc->type,
            'status' => $doc->status,
            'direction' => $doc->direction() > 0 ? 'in' : 'out',
            'warehouse_code' => $doc->warehouse?->code,
            'warehouse_erp_code' => $doc->warehouse?->erp_code,
            'work_order_no' => $doc->workOrder?->order_no,
            'notes' => $doc->notes,
            'erp_reference' => $doc->erp_reference,
            'erp_synced_at' => $doc->erp_synced_at?->toIso8601String(),
            'posted_at' => $doc->posted_at?->toIso8601String(),
            'updated_at' => $doc->updated_at?->toIso8601String(),
            'lines' => $doc->lines->map(fn ($line) => [
                'material_code' => $line->material?->code,
                'product_type_code' => $line->productType?->code,
                'lot_number' => $line->effectiveLotNumber(),
                'quantity' => (float) $line->quantity,
                'unit_of_measure' => $line->unit_of_measure,
                'notes' => $line->notes,
            ])->values(),
        ];
    }
}
