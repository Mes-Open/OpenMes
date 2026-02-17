<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\ResolveIssueRequest;
use App\Models\Issue;
use App\Services\IssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    public function __construct(
        protected IssueService $issueService
    ) {}

    /**
     * Get all issues (with optional filters).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'status' => ['nullable', 'string', 'in:OPEN,ACKNOWLEDGED,RESOLVED,CLOSED'],
        ]);

        $query = Issue::with(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep'])
            ->orderBy('reported_at', 'desc');

        // Filter by line_id
        if ($request->filled('line_id')) {
            $query->whereHas('workOrder', function ($q) use ($request) {
                $q->where('line_id', $request->line_id);
            });
        }

        // Filter by work_order_id
        if ($request->filled('work_order_id')) {
            $query->where('work_order_id', $request->work_order_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->status($request->status);
        }

        $issues = $query->paginate(20);

        return response()->json([
            'data' => $issues->items(),
            'meta' => [
                'current_page' => $issues->currentPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
                'last_page' => $issues->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single issue.
     */
    public function show(Issue $issue): JsonResponse
    {
        $issue->load(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep']);

        return response()->json([
            'data' => $issue,
        ]);
    }

    /**
     * Create a new issue.
     */
    public function store(CreateIssueRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['reported_by_id'] = $request->user()->id;

        $issue = $this->issueService->createIssue($data);

        return response()->json([
            'data' => $issue,
            'message' => 'Issue reported successfully',
        ], 201);
    }

    /**
     * Update an issue.
     */
    public function update(UpdateIssueRequest $request, Issue $issue): JsonResponse
    {
        $issue->update($request->validated());

        return response()->json([
            'data' => $issue->fresh(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep']),
            'message' => 'Issue updated successfully',
        ]);
    }

    /**
     * Acknowledge an issue.
     */
    public function acknowledge(Issue $issue, Request $request): JsonResponse
    {
        try {
            $updatedIssue = $this->issueService->acknowledgeIssue($issue, $request->user()->id);

            return response()->json([
                'data' => $updatedIssue,
                'message' => 'Issue acknowledged successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Resolve an issue.
     */
    public function resolve(ResolveIssueRequest $request, Issue $issue): JsonResponse
    {
        try {
            $updatedIssue = $this->issueService->resolveIssue(
                $issue,
                $request->validated()['resolution_notes']
            );

            return response()->json([
                'data' => $updatedIssue,
                'message' => 'Issue resolved successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Close an issue.
     */
    public function close(Issue $issue): JsonResponse
    {
        try {
            $updatedIssue = $this->issueService->closeIssue($issue);

            return response()->json([
                'data' => $updatedIssue,
                'message' => 'Issue closed successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get issue statistics for a line.
     */
    public function lineStats(Request $request): JsonResponse
    {
        $request->validate([
            'line_id' => ['required', 'integer', 'exists:lines,id'],
        ]);

        $stats = $this->issueService->getLineIssueStats($request->line_id);

        return response()->json([
            'data' => $stats,
        ]);
    }
}
