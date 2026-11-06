<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\BuildsCursorMeta;
use App\Http\Controllers\Api\V1\Erp\Concerns\FiltersSince;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\MasterDataExportRequest;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * OpenMES → ERP: read-only master-data dictionaries — production lines,
 * warehouses and product types — so an integration can offer the OpenMES codes
 * in a picker instead of having them typed in by hand. Requires the
 * `erp:masterdata:read` scope; rate limited via the `erp-read` limiter.
 *
 * Cursor-paginated like the other ERP exports. Tenant scoping is applied by the
 * models' TenantScope from the API key's tenant (TenantContext, set by
 * auth.apikey); soft-deleted rows are excluded by the SoftDeletes scope.
 */
class MasterDataExportController extends Controller
{
    use BuildsCursorMeta;
    use FiltersSince;

    public function lines(MasterDataExportRequest $request): JsonResponse
    {
        $query = Line::query()->select(['id', 'code', 'name', 'description', 'is_active', 'updated_at']);

        return $this->respond($request, $query, fn (Line $line) => [
            'code' => $line->code,
            'name' => $line->name,
            'description' => $line->description,
            'is_active' => (bool) $line->is_active,
            'updated_at' => $line->updated_at?->toIso8601String(),
        ]);
    }

    /** Only routed while the Warehouses module is on (404 otherwise). */
    public function warehouses(MasterDataExportRequest $request): JsonResponse
    {
        $query = Warehouse::query()->select(['id', 'code', 'erp_code', 'name', 'kind', 'is_default', 'is_active', 'updated_at']);

        return $this->respond($request, $query, fn (Warehouse $warehouse) => [
            'code' => $warehouse->code,
            'erp_code' => $warehouse->erp_code,
            'name' => $warehouse->name,
            'kind' => $warehouse->kind,
            'is_default' => (bool) $warehouse->is_default,
            'is_active' => (bool) $warehouse->is_active,
            'updated_at' => $warehouse->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * `has_active_process_template` lets the integration warn up front that a
     * work order for this product would have no routing to follow, instead of
     * finding out from an import error. Computed in the same query (no N+1).
     */
    public function productTypes(MasterDataExportRequest $request): JsonResponse
    {
        $query = ProductType::query()
            ->select(['id', 'code', 'name', 'unit_of_measure', 'is_active', 'updated_at'])
            ->withExists(['processTemplates as has_active_process_template' => fn ($q) => $q->where('is_active', true)]);

        return $this->respond($request, $query, fn (ProductType $type) => [
            'code' => $type->code,
            'name' => $type->name,
            'unit_of_measure' => $type->unit_of_measure,
            'is_active' => (bool) $type->is_active,
            'has_active_process_template' => (bool) $type->has_active_process_template,
            'updated_at' => $type->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Apply the shared `since` / `active` filters, page by primary key and
     * present each row through the given allowlist.
     */
    private function respond(MasterDataExportRequest $request, Builder $query, \Closure $present): JsonResponse
    {
        // Incremental sync: everything changed at/after the ERP's last poll.
        $this->applySince($query, $request);

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $page = $query->orderBy('id')->cursorPaginate($request->perPage())->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map($present)->values(),
            'meta' => $this->cursorMeta($page),
        ]);
    }
}
