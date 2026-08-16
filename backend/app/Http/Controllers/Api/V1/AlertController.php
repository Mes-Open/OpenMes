<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;

/**
 * Admin alerts for the mobile/tablet app, mirroring the web alerts page
 * (Pages/admin/alerts/Index.jsx). The web page derives the four lists
 * client-side from synced collections; here we compute them server-side:
 * blocking issues, non-blocking (open) issues, overdue work orders, blocked
 * work orders — plus the combined total used for the nav badge.
 */
class AlertController extends Controller
{
    private const OPEN_STATUSES = [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED];

    public function index(): JsonResponse
    {
        $openIssues = Issue::with(['issueType', 'workOrder', 'reportedBy'])
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Issue $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'description' => $i->description,
                'status' => $i->status,
                'type_name' => optional($i->issueType)->name,
                'is_blocking' => (bool) optional($i->issueType)->is_blocking,
                'order' => $i->workOrder ? ['id' => $i->workOrder->id, 'order_no' => $i->workOrder->order_no] : null,
                'reporter_name' => optional($i->reportedBy)->name,
                'created_at' => optional($i->created_at)->toIso8601String(),
            ]);

        $blocking = $openIssues->where('is_blocking', true)->values();
        $nonBlocking = $openIssues->where('is_blocking', false)->values();

        $overdue = WorkOrder::with('line')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->orderBy('due_date')
            ->get()
            ->map(fn (WorkOrder $o) => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'line_name' => optional($o->line)->name,
                'due_date' => optional($o->due_date)->toIso8601String(),
                'status' => $o->status,
            ]);

        $blocked = WorkOrder::with('line')
            ->where('status', WorkOrder::STATUS_BLOCKED)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (WorkOrder $o) => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'line_name' => optional($o->line)->name,
                'updated_at' => optional($o->updated_at)->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'blocking_issues' => $blocking,
                'non_blocking_issues' => $nonBlocking,
                'overdue_orders' => $overdue,
                'blocked_orders' => $blocked,
                'total' => $blocking->count() + $nonBlocking->count() + $overdue->count() + $blocked->count(),
            ],
        ]);
    }
}
