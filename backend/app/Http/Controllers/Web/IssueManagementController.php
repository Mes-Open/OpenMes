<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueActionRequest;
use App\Http\Requests\UpdateIssueActionRequest;
use App\Models\Issue;
use App\Models\IssueAction;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Quality\IssueActionService;
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
            'lineNames' => Line::pluck('name', 'id'),
            'reporterNames' => User::pluck('name', 'id'),
            'workOrderNos' => WorkOrder::pluck('order_no', 'id'),
        ]);
    }

    public function acknowledge(Request $request, Issue $issue)
    {
        if ($issue->status !== Issue::STATUS_OPEN) {
            return redirect()->back()->with('error', 'Issue is not in OPEN status.');
        }

        $issue->update([
            'status' => Issue::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Issue acknowledged.');
    }

    public function resolve(Request $request, Issue $issue)
    {
        $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        if (! in_array($issue->status, [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])) {
            return redirect()->back()->with('error', 'Issue is already resolved or closed.');
        }

        $issue->update([
            'status' => Issue::STATUS_RESOLVED,
            'resolved_at' => now(),
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

        // Closure gate: every corrective/preventive action must be verified.
        if ($issue->hasUnverifiedActions()) {
            return redirect()->back()->with('error', 'Cannot close: all corrective/preventive actions must be verified first.');
        }

        $issue->update([
            'status' => Issue::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Issue closed.');
    }

    // ── Corrective / preventive actions (CAPA) ───────────────────────────────

    /** JSON list of an issue's actions (for the actions modal). */
    public function actions(Issue $issue)
    {
        return response()->json([
            'actions' => $this->serializeActions($issue),
        ]);
    }

    public function storeAction(StoreIssueActionRequest $request, Issue $issue, IssueActionService $service)
    {
        $service->create($issue, $request->validated());

        return response()->json(['actions' => $this->serializeActions($issue->fresh())], 201);
    }

    public function updateAction(UpdateIssueActionRequest $request, IssueAction $action)
    {
        $action->update($request->validated());

        return response()->json(['actions' => $this->serializeActions($action->issue)]);
    }

    public function startAction(IssueAction $action, IssueActionService $service)
    {
        return $this->runAction(fn () => $service->start($action), $action);
    }

    public function completeAction(Request $request, IssueAction $action, IssueActionService $service)
    {
        $notes = $request->input('notes');

        return $this->runAction(fn () => $service->complete($action, $request->user(), $notes), $action);
    }

    public function verifyAction(Request $request, IssueAction $action, IssueActionService $service)
    {
        return $this->runAction(fn () => $service->verify($action, $request->user()), $action);
    }

    public function destroyAction(IssueAction $action)
    {
        $issue = $action->issue;
        $action->delete();

        return response()->json(['actions' => $this->serializeActions($issue)]);
    }

    /** Run a transition, mapping DomainException to a 422 with the actions list. */
    private function runAction(callable $fn, IssueAction $action)
    {
        try {
            $fn();
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['actions' => $this->serializeActions($action->issue)]);
    }

    /** @return array<int, array<string, mixed>> */
    private function serializeActions(Issue $issue): array
    {
        return $issue->actions()
            ->with(['assignedTo:id,name', 'completedBy:id,name', 'verifiedBy:id,name'])
            ->orderBy('id')
            ->get()
            ->map(fn (IssueAction $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'title' => $a->title,
                'description' => $a->description,
                'status' => $a->status,
                'assigned_to' => $a->assignedTo?->name,
                'due_date' => $a->due_date?->toDateString(),
                'completed_by' => $a->completedBy?->name,
                'verified_by' => $a->verifiedBy?->name,
                'notes' => $a->notes,
            ])
            ->all();
    }
}
