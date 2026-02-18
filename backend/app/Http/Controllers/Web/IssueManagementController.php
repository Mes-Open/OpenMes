<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Line;
use App\Models\WorkOrder;
use Illuminate\Http\Request;

/**
 * Shared issues management — accessible by both Admin and Supervisor.
 */
class IssueManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Issue::with(['workOrder.line', 'issueType', 'reportedBy', 'assignedTo'])
            ->orderByRaw("CASE status
                WHEN 'OPEN'         THEN 1
                WHEN 'ACKNOWLEDGED' THEN 2
                WHEN 'RESOLVED'     THEN 3
                ELSE 4 END")
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('line_id')) {
            $query->whereHas('workOrder', fn($q) => $q->where('line_id', $request->line_id));
        }
        if ($request->filled('blocking')) {
            $query->whereHas('issueType', fn($q) => $q->where('is_blocking', true))
                  ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED]);
        }

        $issues = $query->paginate(25)->withQueryString();
        $lines  = Line::orderBy('name')->get();

        return view('shared.issues.index', compact('issues', 'lines'));
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
