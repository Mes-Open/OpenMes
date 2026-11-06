<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\BuildsCursorMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\QualityExportRequest;
use App\Models\Issue;
use App\Support\Integration\QualityIssuePresenter;
use Illuminate\Http\JsonResponse;

/**
 * OpenMES → ERP: quality / non-conformance export. Emits issues (defects,
 * non-conformances) with their disposition, responsibility source and
 * non-conforming quantity, cursor-paginated for incremental polling. Requires
 * the `erp:quality:read` scope; rate limited via the `erp-read` limiter.
 * Tenant scoping is applied automatically from the API key's tenant.
 */
class QualityExportController extends Controller
{
    use BuildsCursorMeta;

    public function issues(QualityExportRequest $request): JsonResponse
    {
        $line = $request->input('line');

        // Issue itself is not tenant-scoped, so isolation is enforced through its
        // work order (which is): whereHas('workOrder') applies WorkOrder's
        // TenantScope, restricting the export to non-conformances raised against
        // the API key's tenant's production orders.
        $query = Issue::query()
            ->with(QualityIssuePresenter::RELATIONS)
            ->whereHas('workOrder', function ($q) use ($line) {
                if ($line) {
                    $q->whereHas('line', fn ($l) => $l->where('code', $line));
                }
            })
            ->orderBy('id');

        if ($since = $request->input('since')) {
            $query->where('updated_at', '>=', $since);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $page = $query->cursorPaginate($request->perPage())->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (Issue $issue) => QualityIssuePresenter::present($issue)),
            'meta' => $this->cursorMeta($page),
        ]);
    }
}
