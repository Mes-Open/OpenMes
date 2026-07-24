<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\BuildsCursorMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\QualityExportRequest;
use App\Models\Issue;
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
            ->with(['workOrder:id,order_no,line_id', 'issueType:id,name,severity'])
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
            'data' => collect($page->items())->map(fn (Issue $issue) => $this->present($issue)),
            'meta' => $this->cursorMeta($page),
        ]);
    }

    private function present(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'title' => $issue->title,
            'status' => $issue->status,
            'disposition' => $issue->disposition,
            'nc_source' => $issue->nc_source,
            'non_conforming_qty' => $issue->non_conforming_qty !== null ? (float) $issue->non_conforming_qty : null,
            'issue_type' => $issue->issueType?->name,
            'severity' => $issue->issueType?->severity,
            'work_order_no' => $issue->workOrder?->order_no,
            'root_cause' => $issue->root_cause,
            'containment_action' => $issue->containment_action,
            'reported_at' => $issue->reported_at?->toIso8601String(),
            'acknowledged_at' => $issue->acknowledged_at?->toIso8601String(),
            'resolved_at' => $issue->resolved_at?->toIso8601String(),
            'closed_at' => $issue->closed_at?->toIso8601String(),
            'updated_at' => $issue->updated_at?->toIso8601String(),
        ];
    }
}
