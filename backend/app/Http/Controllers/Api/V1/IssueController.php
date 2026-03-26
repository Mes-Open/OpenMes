<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\ResolveIssueRequest;
use App\Models\Issue;
use App\Contracts\Services\IssueServiceInterface;
use App\Http\Resources\IssueResource;
use App\Traits\StandardApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    use StandardApiResponse;

    public function __construct(
        protected IssueServiceInterface $issueService
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

        return $this->paginated($issues, IssueResource::class);
    }

    /**
     * Get a single issue.
     */
    public function show(Issue $issue): JsonResponse
    {
        $issue->load(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep']);

        return (new IssueResource($issue))->response();
    }

    /**
     * Create a new issue.
     */
    public function store(CreateIssueRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['reported_by_id'] = $request->user()->id;

        $issue = $this->issueService->createIssue($data);

        return $this->success(
            new IssueResource($issue),
            'Issue reported successfully',
            201
        );
    }

    /**
     * Update an issue.
     */
    public function update(UpdateIssueRequest $request, Issue $issue): JsonResponse
    {
        $issue->update($request->validated());

        return $this->success(
            new IssueResource($issue->fresh(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep'])),
            'Issue updated successfully'
        );
    }

    /**
     * Acknowledge an issue.
     */
    public function acknowledge(Issue $issue, Request $request): JsonResponse
    {
        try {
            $updatedIssue = $this->issueService->acknowledgeIssue($issue, $request->user()->id);

            return $this->success(
                new IssueResource($updatedIssue),
                'Issue acknowledged successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
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

            return $this->success(
                new IssueResource($updatedIssue),
                'Issue resolved successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Close an issue.
     */
    public function close(Issue $issue): JsonResponse
    {
        try {
            $updatedIssue = $this->issueService->closeIssue($issue);

            return $this->success(
                new IssueResource($updatedIssue),
                'Issue closed successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
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

        return $this->success($stats);
    }
}
