<?php

namespace Tests\Unit\Services;

use App\Models\Batch;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\WorkOrder;
use App\Services\IssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueServiceTest extends TestCase
{
    use RefreshDatabase;

    protected IssueService $service;
    protected WorkOrder $workOrder;
    protected IssueType $blockingType;
    protected IssueType $nonBlockingType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(IssueService::class);

        $this->workOrder = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $this->blockingType = IssueType::factory()->create([
            'code' => 'BLOCKING',
            'is_blocking' => true,
        ]);

        $this->nonBlockingType = IssueType::factory()->create([
            'code' => 'NON_BLOCKING',
            'is_blocking' => false,
        ]);
    }

    // ── createIssue() ────────────────────────────────────────────────────────

    public function test_create_issue_persists_to_database(): void
    {
        $issue = $this->service->createIssue([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'title'         => 'Test issue',
        ]);

        $this->assertDatabaseHas('issues', [
            'id'    => $issue->id,
            'title' => 'Test issue',
        ]);
    }

    public function test_create_issue_sets_status_open_and_reported_at(): void
    {
        $issue = $this->service->createIssue([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'title'         => 'Test',
        ]);

        $this->assertEquals(Issue::STATUS_OPEN, $issue->status);
        $this->assertNotNull($issue->reported_at);
    }

    public function test_create_blocking_issue_blocks_work_order(): void
    {
        $this->service->createIssue([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'title'         => 'Blocking issue',
        ]);

        $this->assertEquals(WorkOrder::STATUS_BLOCKED, $this->workOrder->fresh()->status);
    }

    public function test_create_non_blocking_issue_does_not_block_work_order(): void
    {
        $this->service->createIssue([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'title'         => 'Non-blocking',
        ]);

        $this->assertEquals(WorkOrder::STATUS_PENDING, $this->workOrder->fresh()->status);
    }

    public function test_create_blocking_issue_on_already_blocked_work_order_keeps_blocked(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);

        $this->service->createIssue([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'title'         => 'Second blocking issue',
        ]);

        $this->assertEquals(WorkOrder::STATUS_BLOCKED, $this->workOrder->fresh()->status);
    }

    public function test_create_blocking_issue_does_not_block_done_work_order(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_DONE]);

        $this->service->createIssue([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'title'         => 'Issue on done WO',
        ]);

        $this->assertEquals(WorkOrder::STATUS_DONE, $this->workOrder->fresh()->status);
    }

    // ── acknowledgeIssue() ───────────────────────────────────────────────────

    public function test_acknowledge_open_issue_changes_status(): void
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);

        $userId = \App\Models\User::factory()->create()->id;
        $updated = $this->service->acknowledgeIssue($issue, $userId);

        $this->assertEquals(Issue::STATUS_ACKNOWLEDGED, $updated->status);
        $this->assertEquals($userId, $updated->assigned_to_id);
        $this->assertNotNull($updated->acknowledged_at);
    }

    public function test_acknowledge_non_open_issue_throws(): void
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_RESOLVED,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->acknowledgeIssue($issue, 1);
    }

    // ── resolveIssue() ───────────────────────────────────────────────────────

    public function test_resolve_open_issue_changes_status(): void
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);

        $updated = $this->service->resolveIssue($issue, 'Fixed it');

        $this->assertEquals(Issue::STATUS_RESOLVED, $updated->status);
        $this->assertEquals('Fixed it', $updated->resolution_notes);
        $this->assertNotNull($updated->resolved_at);
    }

    public function test_resolve_acknowledged_issue_changes_status(): void
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'status'        => Issue::STATUS_ACKNOWLEDGED,
        ]);

        $updated = $this->service->resolveIssue($issue);

        $this->assertEquals(Issue::STATUS_RESOLVED, $updated->status);
    }

    public function test_resolve_last_blocking_issue_unblocks_work_order(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);

        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_ACKNOWLEDGED,
        ]);

        $this->service->resolveIssue($issue);

        $this->assertEquals(WorkOrder::STATUS_PENDING, $this->workOrder->fresh()->status);
    }

    public function test_resolve_blocking_issue_keeps_blocked_when_others_remain(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);

        $issue1 = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_ACKNOWLEDGED,
        ]);

        // Second blocking issue still open
        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);

        $this->service->resolveIssue($issue1);

        $this->assertEquals(WorkOrder::STATUS_BLOCKED, $this->workOrder->fresh()->status);
    }

    public function test_resolve_blocked_work_order_with_active_batch_restores_in_progress(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);

        Batch::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'status'        => Batch::STATUS_IN_PROGRESS,
        ]);

        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);

        $this->service->resolveIssue($issue);

        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
    }

    public function test_resolve_closed_issue_throws(): void
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_CLOSED,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->resolveIssue($issue);
    }

    // ── closeIssue() ─────────────────────────────────────────────────────────

    public function test_close_resolved_issue_changes_status(): void
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'status'        => Issue::STATUS_RESOLVED,
        ]);

        $updated = $this->service->closeIssue($issue);

        $this->assertEquals(Issue::STATUS_CLOSED, $updated->status);
        $this->assertNotNull($updated->closed_at);
    }

    public function test_close_non_resolved_issue_throws(): void
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->closeIssue($issue);
    }

    // ── getWorkOrderIssues() ─────────────────────────────────────────────────

    public function test_get_work_order_issues_returns_only_those_issues(): void
    {
        $otherWorkOrder = WorkOrder::factory()->create();

        Issue::factory()->count(3)->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
        ]);

        Issue::factory()->create([
            'work_order_id' => $otherWorkOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
        ]);

        $issues = $this->service->getWorkOrderIssues($this->workOrder->id);

        $this->assertCount(3, $issues);
    }

    public function test_get_work_order_issues_filters_by_status(): void
    {
        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);
        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'status'        => Issue::STATUS_RESOLVED,
        ]);

        $open = $this->service->getWorkOrderIssues($this->workOrder->id, Issue::STATUS_OPEN);

        $this->assertCount(1, $open);
        $this->assertEquals(Issue::STATUS_OPEN, $open->first()->status);
    }

    // ── getLineIssueStats() ──────────────────────────────────────────────────

    public function test_get_line_issue_stats_returns_correct_counts(): void
    {
        $line = Line::factory()->create();
        $workOrder = WorkOrder::factory()->create(['line_id' => $line->id]);

        Issue::factory()->create([
            'work_order_id' => $workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);
        Issue::factory()->create([
            'work_order_id' => $workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'status'        => Issue::STATUS_RESOLVED,
        ]);
        Issue::factory()->create([
            'work_order_id' => $workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);

        $stats = $this->service->getLineIssueStats($line->id);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['open']);
        $this->assertEquals(1, $stats['resolved']);
        $this->assertEquals(1, $stats['blocking']);
    }
}
