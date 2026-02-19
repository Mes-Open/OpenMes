<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Line;
use App\Models\WorkOrder;
use App\Models\ProductType;
use App\Models\ProcessTemplate;
use App\Models\IssueType;
use App\Models\Issue;
use App\Models\Batch;
use App\Models\BatchStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Line $line;
    protected WorkOrder $workOrder;
    protected IssueType $blockingIssueType;
    protected IssueType $nonBlockingIssueType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('Operator');
        $this->line = Line::factory()->create();
        $this->user->lines()->attach($this->line);

        $productType = ProductType::factory()->create();
        $processTemplate = ProcessTemplate::factory()->create([
            'product_type_id' => $productType->id,
        ]);

        $this->workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $productType->id,
            'status' => 'PENDING',
        ]);

        $this->blockingIssueType = IssueType::factory()->create([
            'code' => 'TEST_BLOCKING',
            'name' => 'Test Blocking Issue',
            'severity' => 'CRITICAL',
            'is_blocking' => true,
        ]);

        $this->nonBlockingIssueType = IssueType::factory()->create([
            'code' => 'TEST_NON_BLOCKING',
            'name' => 'Test Non-Blocking Issue',
            'severity' => 'MEDIUM',
            'is_blocking' => false,
        ]);
    }

    /** @test */
    public function it_can_create_a_blocking_issue_and_block_work_order()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/issues', [
                'work_order_id' => $this->workOrder->id,
                'issue_type_id' => $this->blockingIssueType->id,
                'title' => 'Test blocking issue',
                'description' => 'This is a test',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'work_order_id',
                    'issue_type_id',
                    'title',
                    'description',
                    'status',
                    'reported_by_id',
                ],
            ]);

        $this->assertDatabaseHas('issues', [
            'work_order_id' => $this->workOrder->id,
            'title' => 'Test blocking issue',
            'status' => 'OPEN',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $this->workOrder->id,
            'status' => 'BLOCKED',
        ]);
    }

    /** @test */
    public function it_can_create_a_non_blocking_issue_without_blocking_work_order()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/issues', [
                'work_order_id' => $this->workOrder->id,
                'issue_type_id' => $this->nonBlockingIssueType->id,
                'title' => 'Test non-blocking issue',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('issues', [
            'work_order_id' => $this->workOrder->id,
            'title' => 'Test non-blocking issue',
            'status' => 'OPEN',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $this->workOrder->id,
            'status' => 'PENDING', // Should NOT be blocked
        ]);
    }

    /** @test */
    public function it_can_acknowledge_an_issue()
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingIssueType->id,
            'status' => 'OPEN',
            'reported_by_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/acknowledge");

        $response->assertStatus(200);

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'ACKNOWLEDGED',
            'assigned_to_id' => $this->user->id,
        ]);

        $issue->refresh();
        $this->assertNotNull($issue->acknowledged_at);
    }

    /** @test */
    public function it_can_resolve_an_issue_and_unblock_work_order()
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingIssueType->id,
            'status' => 'ACKNOWLEDGED',
            'reported_by_id' => $this->user->id,
        ]);

        $this->workOrder->update(['status' => 'BLOCKED']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/resolve", [
                'resolution_notes' => 'Fixed the problem',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'RESOLVED',
            'resolution_notes' => 'Fixed the problem',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $this->workOrder->id,
            'status' => 'PENDING', // Should be unblocked
        ]);

        $issue->refresh();
        $this->assertNotNull($issue->resolved_at);
    }

    /** @test */
    public function it_does_not_unblock_work_order_if_other_blocking_issues_exist()
    {
        $issue1 = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingIssueType->id,
            'status' => 'ACKNOWLEDGED',
        ]);

        $issue2 = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingIssueType->id,
            'status' => 'OPEN', // Another blocking issue
        ]);

        $this->workOrder->update(['status' => 'BLOCKED']);

        // Resolve first issue
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/issues/{$issue1->id}/resolve", [
                'resolution_notes' => 'Fixed',
            ]);

        // Work order should still be blocked
        $this->assertDatabaseHas('work_orders', [
            'id' => $this->workOrder->id,
            'status' => 'BLOCKED',
        ]);

        // Resolve second issue
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/issues/{$issue2->id}/resolve", [
                'resolution_notes' => 'Also fixed',
            ]);

        // Now work order should be unblocked
        $this->assertDatabaseHas('work_orders', [
            'id' => $this->workOrder->id,
            'status' => 'PENDING',
        ]);
    }

    /** @test */
    public function it_can_close_a_resolved_issue()
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingIssueType->id,
            'status' => 'RESOLVED',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/close");

        $response->assertStatus(200);

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'CLOSED',
        ]);

        $issue->refresh();
        $this->assertNotNull($issue->closed_at);
    }

    /** @test */
    public function it_cannot_close_an_unresolved_issue()
    {
        $issue = Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingIssueType->id,
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/close");

        $response->assertStatus(422);

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'OPEN', // Should remain OPEN
        ]);
    }

    /** @test */
    public function it_can_report_problem_on_batch_step()
    {
        $batch = Batch::factory()->create([
            'work_order_id' => $this->workOrder->id,
        ]);

        $batchStep = BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'status' => 'IN_PROGRESS',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/batch-steps/{$batchStep->id}/problem", [
                'issue_type_id' => $this->blockingIssueType->id,
                'title' => 'Problem during step execution',
                'description' => 'Detailed description',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('issues', [
            'work_order_id' => $this->workOrder->id,
            'batch_step_id' => $batchStep->id,
            'title' => 'Problem during step execution',
        ]);
    }

    /** @test */
    public function it_can_filter_issues_by_work_order()
    {
        $workOrder2 = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
        ]);

        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingIssueType->id,
        ]);

        Issue::factory()->create([
            'work_order_id' => $workOrder2->id,
            'issue_type_id' => $this->blockingIssueType->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/issues?work_order_id={$this->workOrder->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function it_can_get_line_issue_stats()
    {
        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingIssueType->id,
            'status' => 'OPEN',
        ]);

        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingIssueType->id,
            'status' => 'RESOLVED',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/issues/stats/line?line_id={$this->line->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total' => 2,
                    'open' => 1,
                    'resolved' => 1,
                ],
            ]);
    }
}
