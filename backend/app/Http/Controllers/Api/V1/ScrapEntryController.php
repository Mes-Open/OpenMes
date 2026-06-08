<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ScrapEntry;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScrapEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScrapEntry::class);

        // Tenant isolation: only entries whose work order is visible under the
        // WorkOrder tenant scope (ScrapEntry itself has no tenant column).
        // No-op for single-tenant installs (tenant scope is inactive there).
        $query = ScrapEntry::query()->whereHas('workOrder')->with(['scrapReason', 'reportedBy', 'workOrder']);
        if ($woId = $request->query('work_order_id')) {
            $query->where('work_order_id', $woId);
        }
        if ($lineId = $request->query('line_id')) {
            $query->whereHas('workOrder', fn ($q) => $q->where('line_id', $lineId));
        }
        if ($reasonId = $request->query('scrap_reason_id')) {
            $query->where('scrap_reason_id', $reasonId);
        }
        if ($from = $request->query('from')) {
            $query->where('reported_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('reported_at', '<=', $to);
        }

        $perPage = max(1, min((int) $request->query('per_page', 30), 100));
        $page = $query->orderByDesc('reported_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * List scrap entries for a single work order, with the work order totals.
     */
    public function forWorkOrder(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('viewAny', ScrapEntry::class);

        $entries = $workOrder->scrapEntries()
            ->with(['scrapReason', 'reportedBy', 'batchStep', 'shift'])
            ->orderByDesc('reported_at')
            ->get();

        return response()->json([
            'data' => $entries,
            'meta' => [
                'work_order_id' => $workOrder->id,
                'total_scrap_qty' => $workOrder->totalScrapQty(),
                'quality_pct' => $workOrder->qualityPct(),
            ],
        ]);
    }

    public function show(ScrapEntry $scrapEntry): JsonResponse
    {
        $this->authorize('view', $scrapEntry);
        $this->assertTenantVisible($scrapEntry);
        $scrapEntry->load(['scrapReason', 'reportedBy', 'workOrder', 'batchStep', 'shift']);

        return response()->json(['data' => $scrapEntry]);
    }

    public function store(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('create', ScrapEntry::class);
        $data = $request->validate([
            'scrap_reason_id' => ['required', 'integer', Rule::exists('scrap_reasons', 'id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'batch_step_id' => ['nullable', 'integer', 'exists:batch_steps,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'notes' => ['nullable', 'string'],
            'reported_at' => ['nullable', 'date'],
        ]);
        $data['work_order_id'] = $workOrder->id;
        $data['reported_by'] = $request->user()->id;
        $data['reported_at'] = $data['reported_at'] ?? now();

        $entry = ScrapEntry::create($data);

        return response()->json([
            'message' => 'Scrap recorded',
            'data' => $entry->load(['scrapReason', 'reportedBy']),
        ], 201);
    }

    public function update(Request $request, ScrapEntry $scrapEntry): JsonResponse
    {
        $this->authorize('update', $scrapEntry);
        $this->assertTenantVisible($scrapEntry);
        $data = $request->validate([
            'scrap_reason_id' => ['sometimes', 'integer', Rule::exists('scrap_reasons', 'id')->where('is_active', true)],
            'quantity' => ['sometimes', 'numeric', 'min:0.01', 'max:99999999'],
            'batch_step_id' => ['sometimes', 'nullable', 'integer', 'exists:batch_steps,id'],
            'shift_id' => ['sometimes', 'nullable', 'integer', 'exists:shifts,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
        $scrapEntry->update($data);

        return response()->json(['message' => 'Scrap entry updated', 'data' => $scrapEntry->fresh(['scrapReason'])]);
    }

    public function destroy(ScrapEntry $scrapEntry): JsonResponse
    {
        $this->authorize('delete', $scrapEntry);
        $this->assertTenantVisible($scrapEntry);
        $scrapEntry->delete();

        return response()->json(['message' => 'Scrap entry deleted']);
    }

    /**
     * Guard against cross-tenant access to a directly-bound scrap entry.
     *
     * ScrapEntry has no tenant column; isolation rides on its work order,
     * which carries the tenant scope. If the entry's work order is not
     * visible to the current tenant, treat the entry as not found.
     * No-op for single-tenant installs (the tenant scope is inactive).
     */
    private function assertTenantVisible(ScrapEntry $scrapEntry): void
    {
        abort_unless($scrapEntry->workOrder()->exists(), 404);
    }
}
