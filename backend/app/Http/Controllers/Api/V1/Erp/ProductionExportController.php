<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\BuildsCursorMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\ProductionExportRequest;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;

/**
 * OpenMES → ERP: production completion export. Emits produced quantities and
 * completion timestamps per work order, cursor-paginated for incremental
 * polling. Requires the `erp:production:read` scope; rate limited via the
 * `erp-read` limiter. Tenant scoping is applied automatically from the API
 * key's tenant (TenantContext).
 */
class ProductionExportController extends Controller
{
    use BuildsCursorMeta;

    public function completions(ProductionExportRequest $request): JsonResponse
    {
        $query = WorkOrder::query()
            ->with(['line:id,code,name', 'productType:id,code,name'])
            ->orderBy('id');

        // Incremental sync: everything changed at/after the ERP's last poll.
        if ($since = $request->input('since')) {
            $query->where('updated_at', '>=', $since);
        }

        // Default to completed orders — the usual ERP "post production" grain.
        $query->where('status', $request->input('status', WorkOrder::STATUS_DONE));

        if ($line = $request->input('line')) {
            $query->whereHas('line', fn ($q) => $q->where('code', $line));
        }

        $page = $query->cursorPaginate($request->perPage())->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (WorkOrder $wo) => $this->present($wo)),
            'meta' => $this->cursorMeta($page),
        ]);
    }

    /**
     * Single work order by id — lets the ERP confirm the current state of one
     * order it previously imported. 404 (via route-model binding) when the id
     * is unknown within the key's tenant.
     */
    public function show(WorkOrder $workOrder): JsonResponse
    {
        $workOrder->load(['line:id,code,name', 'productType:id,code,name']);

        return response()->json(['data' => $this->present($workOrder)]);
    }

    private function present(WorkOrder $wo): array
    {
        return [
            'order_no' => $wo->order_no,
            'customer_order_no' => $wo->customer_order_no,
            'status' => $wo->status,
            'planned_qty' => (float) $wo->planned_qty,
            'produced_qty' => (float) $wo->produced_qty,
            'unit_price' => $wo->unit_price !== null ? (float) $wo->unit_price : null,
            'counting_source' => $wo->counting_source,
            'line_code' => $wo->line?->code,
            'product_type_code' => $wo->productType?->code,
            'completed_at' => $wo->completed_at?->toIso8601String(),
            'updated_at' => $wo->updated_at?->toIso8601String(),
        ];
    }
}
