<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\WorkOrder;

class AlertController extends Controller
{
    public function index()
    {
        // Blocking issues — open or acknowledged, with blocking issue type
        $blockingIssues = Issue::with(['workOrder', 'issueType', 'reportedBy'])
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->whereHas('issueType', fn($q) => $q->where('is_blocking', true))
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
            'overdueOrders',
            'blockedOrders',
        ));
    }

    /**
     * Returns total alert count for navbar badge (called via shared view composer).
     */
    public static function totalCount(): int
    {
        $blocking = Issue::whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->whereHas('issueType', fn($q) => $q->where('is_blocking', true))
            ->count();

        $overdue = WorkOrder::whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->count();

        $blocked = WorkOrder::where('status', WorkOrder::STATUS_BLOCKED)->count();

        return $blocking + $overdue + $blocked;
    }
}
