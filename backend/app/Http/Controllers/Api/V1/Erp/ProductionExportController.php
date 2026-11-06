<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\BuildsCursorMeta;
use App\Http\Controllers\Api\V1\Erp\Concerns\ResolvesErpWorkOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\ProductionExportRequest;
use App\Http\Requests\Api\V1\Erp\ShowErpWorkOrderRequest;
use App\Http\Resources\Erp\ErpChangeRequestPresenter;
use App\Models\WorkOrder;
use App\Services\Erp\WorkOrderSettlementPresenter;
use App\Support\Integration\WorkOrderPresenter;
use Illuminate\Http\JsonResponse;

/**
 * OpenMES → ERP: production completion export. Emits produced quantities and
 * completion timestamps per work order, cursor-paginated for incremental
 * polling, in one, several or all statuses. Requires the `erp:production:read`
 * scope; rate limited via the `erp-read` limiter. Tenant scoping is applied automatically from the API
 * key's tenant (TenantContext).
 *
 * `?include=consumption,scrap,costs` adds settlement sections to each order
 * (see WorkOrderSettlementPresenter); without it the shape is unchanged.
 */
class ProductionExportController extends Controller
{
    use BuildsCursorMeta;
    use ResolvesErpWorkOrder;

    /** Resolved only when sections are requested: its cost service reads settings. */
    private ?WorkOrderSettlementPresenter $settlement = null;

    public function completions(ProductionExportRequest $request): JsonResponse
    {
        $includes = $request->includes();

        $query = WorkOrder::query()
            ->with(WorkOrderPresenter::RELATIONS)
            ->with($includes === [] ? [] : $this->settlement()->relations($includes))
            ->orderBy('id');

        // Incremental sync: everything changed at/after the ERP's last poll.
        if ($since = $request->input('since')) {
            $query->where('updated_at', '>=', $since);
        }

        // Default to completed orders — the usual ERP "post production" grain.
        // `status` may also name several statuses, or `all` for no filter.
        if (($statuses = $request->statuses()) !== null) {
            $query->whereIn('status', $statuses);
        }

        if ($line = $request->input('line')) {
            $query->whereHas('line', fn ($q) => $q->where('code', $line));
        }

        $page = $query->cursorPaginate($request->perPage())->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (WorkOrder $wo) => $this->present($wo, $includes)),
            'meta' => $this->cursorMeta($page),
        ]);
    }

    /**
     * Single work order by id — or by order number with `?by=order_no` — so the ERP
     * can confirm the current state of an order it imported, including every change
     * request raised against it and how each was decided. 404 when the order is
     * unknown within the key's tenant.
     *
     * The model is resolved here rather than via implicit route-model binding on
     * purpose: SubstituteBindings runs before this route's API-key middleware
     * (which is not in the framework's middleware-priority list, unlike
     * auth:sanctum), so a bound model would be looked up before TenantContext is
     * set — with TenantScope disabled — and could return another tenant's order.
     * Resolving in the controller runs after the middleware, so the tenant scope
     * is always applied.
     */
    public function show(ShowErpWorkOrderRequest $request, string $workOrder): JsonResponse
    {
        $includes = $request->includes();

        $order = $this->resolveWorkOrder($workOrder, $request->lookup(), [
            ...WorkOrderPresenter::RELATIONS,
            'changeRequests' => fn ($q) => $q->orderBy('id'),
            ...($includes === [] ? [] : $this->settlement()->relations($includes)),
        ]);

        // present() reads the order number off each request; it is this order.
        $order->changeRequests->each->setRelation('workOrder', $order);
        $presenter = new ErpChangeRequestPresenter($order->changeRequests);

        return response()->json(['data' => [
            ...$this->present($order, $includes),
            'change_requests' => $order->changeRequests->map(fn ($cr) => $presenter->present($cr))->values(),
        ]]);
    }

    /**
     * The shared integration shape, plus the requested settlement sections.
     *
     * @param  list<string>  $includes  requested settlement sections
     */
    private function present(WorkOrder $wo, array $includes = []): array
    {
        $base = WorkOrderPresenter::present($wo);

        return $includes === [] ? $base : array_merge($base, $this->settlement()->sections($wo, $includes));
    }

    private function settlement(): WorkOrderSettlementPresenter
    {
        return $this->settlement ??= app(WorkOrderSettlementPresenter::class);
    }
}
