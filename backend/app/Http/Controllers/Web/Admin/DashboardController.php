<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Line;
use App\Models\WorkOrder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $lines      = Line::where('is_active', true)->orderBy('name')->get();
        $selectedLineId = $request->query('line_id') ?: null;

        // Base queries — optionally scoped to a single line
        $woBase    = WorkOrder::query();
        $issueBase = Issue::query();

        if ($selectedLineId) {
            $woBase->where('line_id', $selectedLineId);
            $issueBase->whereHas('workOrder', fn($q) => $q->where('line_id', $selectedLineId));
        }

        $stats = [
            'total_work_orders' => (clone $woBase)->count(),
            'pending'           => (clone $woBase)->where('status', WorkOrder::STATUS_PENDING)->count(),
            // ACCEPTED + IN_PROGRESS are both "active/running"
            'in_progress'       => (clone $woBase)
                                        ->whereIn('status', [WorkOrder::STATUS_ACCEPTED, WorkOrder::STATUS_IN_PROGRESS])
                                        ->count(),
            'blocked'           => (clone $woBase)->where('status', WorkOrder::STATUS_BLOCKED)->count(),
            'done'              => (clone $woBase)->where('status', WorkOrder::STATUS_DONE)->count(),
            // Use completed_at (set explicitly on completion), not updated_at
            'done_today'        => (clone $woBase)
                                        ->where('status', WorkOrder::STATUS_DONE)
                                        ->whereNotNull('completed_at')
                                        ->whereDate('completed_at', today())
                                        ->count(),
            'open_issues'       => (clone $issueBase)
                                        ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
                                        ->count(),
            'blocking_issues'   => (clone $issueBase)->blocking()->count(),
            'active_lines'      => Line::where('is_active', true)->count(),
        ];

        $recentWorkOrders = (clone $woBase)
            ->with(['line', 'productType'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentIssues = (clone $issueBase)
            ->with(['workOrder', 'issueType', 'reportedBy'])
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'recentWorkOrders', 'recentIssues',
            'lines', 'selectedLineId'
        ));
    }
}
