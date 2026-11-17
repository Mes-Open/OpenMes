<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\BuildsCursorMeta;
use App\Http\Controllers\Api\V1\Erp\Concerns\FiltersSince;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\ErpChangeRequestExportRequest;
use App\Http\Resources\Erp\ErpChangeRequestPresenter;
use App\Models\WorkOrderChangeRequest;
use Illuminate\Http\JsonResponse;

/**
 * OpenMES → ERP: change-request feed.
 *
 * How the ERP learns what a manager decided about the updates and cancellations it
 * sent for orders already in production: it polls `?since=<last sync>` and follows
 * `next_cursor`. Every request is listed — the ERP's own and the ones raised in
 * OpenMES — because both change the order the ERP planned.
 *
 * Requires the `erp:production:read` scope; rate limited via the `erp-read`
 * limiter. Tenant scoping is applied automatically from the API key's tenant.
 */
class ChangeRequestExportController extends Controller
{
    use BuildsCursorMeta;
    use FiltersSince;

    public function index(ErpChangeRequestExportRequest $request): JsonResponse
    {
        $query = WorkOrderChangeRequest::query()
            ->with('workOrder:id,order_no')
            ->orderBy('id');

        $this->applySince($query, $request);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $page = $query->cursorPaginate($request->perPage())->withQueryString();
        $presenter = new ErpChangeRequestPresenter($page->items());

        return response()->json([
            'data' => collect($page->items())->map(fn (WorkOrderChangeRequest $cr) => $presenter->present($cr))->values(),
            'meta' => $this->cursorMeta($page),
        ]);
    }
}
