<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Shared issues management — accessible by both Admin and Supervisor.
 */
class IssueManagementController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('shared/issues/Index', [
            'issueTypeNames' => IssueType::pluck('name', 'id'),
            'lineNames'      => Line::pluck('name', 'id'),
            'reporterNames'  => User::pluck('name', 'id'),
            'workOrderNos'   => WorkOrder::pluck('order_no', 'id'),
        ]);
    }

    public function acknowledge(Request $request, Issue $issue)
    {
        if ($issue->status !== Issue::STATUS_OPEN) {
            return redirect()->back()->with('error', 'Issue is not in OPEN status.');
        }

        $issue->update([
            'status'          => Issue::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Issue acknowledged.');
    }

    public function resolve(Request $request, Issue $issue)
    {
        $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        if (!in_array($issue->status, [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])) {
            return redirect()->back()->with('error', 'Issue is already resolved or closed.');
        }

        $issue->update([
            'status'           => Issue::STATUS_RESOLVED,
            'resolved_at'      => now(),
            'resolution_notes' => $request->input('resolution_notes'),
        ]);

        // Check if work order was blocked and can now be unblocked
        $workOrder = $issue->workOrder;
        if ($workOrder && $workOrder->status === WorkOrder::STATUS_BLOCKED) {
            if ($workOrder->openBlockingIssues()->isEmpty()) {
                $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
            }
        }

        return redirect()->back()->with('success', 'Issue resolved.');
    }

    public function close(Issue $issue)
    {
        if ($issue->status !== Issue::STATUS_RESOLVED) {
            return redirect()->back()->with('error', 'Only resolved issues can be closed.');
        }

        $issue->update([
            'status'    => Issue::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Issue closed.');
    }
}
