<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\WorkOrder;

class AlertController extends Controller
{
    public function index()
    {
        // ALL open issues — blocking first, then non-blocking
        $blockingIssues = Issue::with(['workOrder', 'issueType', 'reportedBy'])
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->whereHas('issueType', fn($q) => $q->where('is_blocking', true))
            ->orderBy('created_at', 'desc')
            ->get();

        $nonBlockingIssues = Issue::with(['workOrder', 'issueType', 'reportedBy'])
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->where(function ($q) {
                $q->whereHas('issueType', fn($q2) => $q2->where('is_blocking', false))
                  ->orWhereDoesntHave('issueType');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Overdue work orders — past due_date, not terminal
        $overdueOrders = WorkOrder::with('line')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->orderBy('due_date')
            ->get();

        // Blocked work orders
        $blockedOrders = WorkOrder::with('line')
            ->where('status', WorkOrder::STATUS_BLOCKED)
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.alerts.index', compact(
            'blockingIssues',
            'nonBlockingIssues',
            'overdueOrders',
            'blockedOrders',
        ));
    }

    /**
     * JSON endpoint for real-time polling.
     */
    public function check()
    {
        return response()->json([
            'total' => static::totalCount(),
            'latest_issue_at' => Issue::whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
                ->max('created_at'),
        ]);
    }

    /**
     * Returns total alert count for navbar badge (called via shared view composer).
     */
    public static function totalCount(): int
    {
        $allOpenIssues = Issue::whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->count();

        $overdue = WorkOrder::whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->count();

        $blocked = WorkOrder::where('status', WorkOrder::STATUS_BLOCKED)->count();

        return $allOpenIssues + $overdue + $blocked;
    }
}
