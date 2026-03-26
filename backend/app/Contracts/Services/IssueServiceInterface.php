<?php

namespace App\Contracts\Services;

use App\Models\Issue;

interface IssueServiceInterface
{
    public function createIssue(array $data): Issue;
    public function acknowledgeIssue(Issue $issue, int $userId): Issue;
    public function resolveIssue(Issue $issue, string $resolutionNotes = null): Issue;
    public function closeIssue(Issue $issue): Issue;
    public function getWorkOrderIssues(int $workOrderId, ?string $status = null);
    public function getLineIssues(int $lineId, ?string $status = null);
    public function getLineIssueStats(int $lineId): array;
}
