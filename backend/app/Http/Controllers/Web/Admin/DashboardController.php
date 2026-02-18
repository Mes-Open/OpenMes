<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Line;
use App\Models\WorkOrder;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_work_orders'   => WorkOrder::count(),
            'pending'             => WorkOrder::where('status', WorkOrder::STATUS_PENDING)->count(),
            'in_progress'         => WorkOrder::where('status', WorkOrder::STATUS_IN_PROGRESS)->count(),
            'blocked'             => WorkOrder::where('status', WorkOrder::STATUS_BLOCKED)->count(),
            'done'                => WorkOrder::where('status', WorkOrder::STATUS_DONE)->count(),
            'open_issues'         => Issue::whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])->count(),
            'blocking_issues'     => Issue::blocking()->count(),
            'active_lines'        => Line::where('is_active', true)->count(),
            'done_today'          => WorkOrder::where('status', WorkOrder::STATUS_DONE)
                                        ->whereDate('updated_at', today())->count(),
        ];

        $recentWorkOrders = WorkOrder::with(['line', 'productType'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        $recentIssues = Issue::with(['workOrder', 'issueType', 'reportedBy'])
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentWorkOrders', 'recentIssues'));
    }
}
