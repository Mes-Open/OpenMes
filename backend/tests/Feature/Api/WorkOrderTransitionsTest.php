<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTransitionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $supervisor;
    protected User $operator;
    protected string $adminToken;
    protected string $supervisorToken;
    protected string $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->supervisorToken = $this->supervisor->createToken('test')->plainTextToken;
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;
    }

    private function authAdmin() { return $this->withHeader('Authorization', "Bearer {$this->adminToken}"); }
    private function authSupervisor() { return $this->withHeader('Authorization', "Bearer {$this->supervisorToken}"); }
    private function authOperator() { return $this->withHeader('Authorization', "Bearer {$this->operatorToken}"); }

    private function makeWO(string $status = WorkOrder::STATUS_PENDING): WorkOrder
    {
        return WorkOrder::factory()->create(['status' => $status]);
    }

    // ── Accept ─────────────────────────────────────────────────────────────

    public function test_accept_pending_to_accepted(): void
    {
        $wo = $this->makeWO();
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/accept")
            ->assertStatus(200);
        $this->assertEquals(WorkOrder::STATUS_ACCEPTED, $wo->fresh()->status);
    }

    public function test_accept_rejected_when_not_pending(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_IN_PROGRESS);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/accept")
            ->assertStatus(422);
    }

    // ── Reject ─────────────────────────────────────────────────────────────

    public function test_reject_pending_or_accepted(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_PENDING);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/reject")->assertStatus(200);
        $this->assertEquals(WorkOrder::STATUS_REJECTED, $wo->fresh()->status);

        $wo2 = $this->makeWO(WorkOrder::STATUS_ACCEPTED);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo2->id}/reject")->assertStatus(200);
    }

    public function test_reject_rejected_when_in_progress(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_IN_PROGRESS);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/reject")->assertStatus(422);
    }

    // ── Cancel ─────────────────────────────────────────────────────────────

    public function test_cancel_works_from_active_states(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_IN_PROGRESS);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/cancel")->assertStatus(200);
        $this->assertEquals(WorkOrder::STATUS_CANCELLED, $wo->fresh()->status);
    }

    public function test_cancel_rejected_when_terminal(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_DONE);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/cancel")->assertStatus(422);
    }

    // ── Pause / Resume ─────────────────────────────────────────────────────

    public function test_pause_in_progress(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_IN_PROGRESS);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/pause")->assertStatus(200);
        $this->assertEquals(WorkOrder::STATUS_PAUSED, $wo->fresh()->status);
    }

    public function test_resume_paused(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_PAUSED);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/resume")->assertStatus(200);
        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $wo->fresh()->status);
    }

    public function test_resume_blocked(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_BLOCKED);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/resume")->assertStatus(200);
    }

    // ── Reopen / Complete ──────────────────────────────────────────────────

    public function test_reopen_done(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_DONE);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/reopen")->assertStatus(200);
        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $wo->fresh()->status);
    }

    public function test_reopen_pending_rejected(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_PENDING);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/reopen")->assertStatus(422);
    }

    public function test_complete_in_progress(): void
    {
        $wo = $this->makeWO(WorkOrder::STATUS_IN_PROGRESS);
        $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/complete")->assertStatus(200);
        $this->assertEquals(WorkOrder::STATUS_DONE, $wo->fresh()->status);
    }

    // ── Authorization ──────────────────────────────────────────────────────

    public function test_operator_cannot_transition(): void
    {
        $wo = $this->makeWO();
        $this->authOperator()->postJson("/api/v1/work-orders/{$wo->id}/accept")
            ->assertStatus(403);
    }

    // ── Batches ────────────────────────────────────────────────────────────

    public function test_admin_can_update_pending_batch(): void
    {
        $wo = $this->makeWO();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'status' => Batch::STATUS_PENDING, 'target_qty' => 10]);

        $this->authAdmin()->patchJson("/api/v1/batches/{$batch->id}", ['target_qty' => 25])
            ->assertStatus(200);
        $this->assertEquals(25, $batch->fresh()->target_qty);
    }

    public function test_cannot_update_in_progress_batch(): void
    {
        $wo = $this->makeWO();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'status' => Batch::STATUS_IN_PROGRESS]);
        $this->authAdmin()->patchJson("/api/v1/batches/{$batch->id}", ['target_qty' => 99])
            ->assertStatus(422);
    }

    public function test_admin_can_cancel_batch(): void
    {
        $wo = $this->makeWO();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'status' => Batch::STATUS_IN_PROGRESS]);

        $this->authAdmin()->postJson("/api/v1/batches/{$batch->id}/cancel")->assertStatus(200);
        $this->assertEquals(Batch::STATUS_CANCELLED, $batch->fresh()->status);
    }

    public function test_admin_can_delete_pending_batch_with_no_started_steps(): void
    {
        $wo = $this->makeWO();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'status' => Batch::STATUS_PENDING]);

        $this->authAdmin()->deleteJson("/api/v1/batches/{$batch->id}")->assertStatus(200);
        $this->assertDatabaseMissing('batches', ['id' => $batch->id]);
    }

    public function test_cannot_delete_batch_with_started_steps(): void
    {
        $wo = $this->makeWO();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'status' => Batch::STATUS_PENDING]);
        BatchStep::factory()->create(['batch_id' => $batch->id, 'status' => BatchStep::STATUS_IN_PROGRESS]);

        $this->authAdmin()->deleteJson("/api/v1/batches/{$batch->id}")->assertStatus(422);
    }

    // ── Issue delete ───────────────────────────────────────────────────────

    public function test_admin_can_delete_issue(): void
    {
        $wo = $this->makeWO();
        $issueType = IssueType::factory()->create();
        $issue = Issue::factory()->create([
            'work_order_id' => $wo->id,
            'issue_type_id' => $issueType->id,
            'reported_by_id' => $this->operator->id,
        ]);

        $this->authAdmin()->deleteJson("/api/v1/issues/{$issue->id}")->assertStatus(200);
        $this->assertDatabaseMissing('issues', ['id' => $issue->id]);
    }

    public function test_supervisor_cannot_delete_issue(): void
    {
        $wo = $this->makeWO();
        $issueType = IssueType::factory()->create();
        $issue = Issue::factory()->create([
            'work_order_id' => $wo->id,
            'issue_type_id' => $issueType->id,
            'reported_by_id' => $this->operator->id,
        ]);

        $this->authSupervisor()->deleteJson("/api/v1/issues/{$issue->id}")->assertStatus(403);
    }
}
