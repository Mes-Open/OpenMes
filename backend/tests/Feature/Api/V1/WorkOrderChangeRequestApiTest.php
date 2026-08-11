<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ChangeRequestStatus;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The controlled change workflow (#182): raise → review → apply → resume, and the
 * guarantee underneath it — an applied change never rewrites executed work.
 */
class WorkOrderChangeRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;

    protected User $operator;

    protected string $supervisorToken;

    protected string $operatorToken;

    protected WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->supervisorToken = $this->supervisor->createToken('test')->plainTextToken;

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;

        $this->workOrder = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'planned_qty' => 100,
            'produced_qty' => 35,
        ]);
        $this->operator->lines()->attach($this->workOrder->line_id);
    }

    /**
     * Swap the bearer token for the next request.
     *
     * forgetGuards() is what makes switching users mid-test actually switch them:
     * the guard resolves the user once and keeps it for the rest of the test,
     * so without this an authorization test would silently re-use the first caller.
     */
    private function asUser(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    private function asSupervisor(): self
    {
        return $this->asUser($this->supervisorToken);
    }

    private function asOperator(): self
    {
        return $this->asUser($this->operatorToken);
    }

    /** Stop the order for an engineering change — the state a change request starts from. */
    private function stopForChange(): void
    {
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'ENGINEERING_CHANGE',
            'reason' => 'Revision must change before continuing.',
            'requires_change' => true,
        ])->assertStatus(201);
    }

    private function createRequest(array $overrides = []): WorkOrderChangeRequest
    {
        $response = $this->asSupervisor()->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/change-requests",
            array_merge([
                'title' => 'Increase planned quantity',
                'reason' => 'Customer extended the order.',
                'proposed' => ['planned_qty' => 150],
            ], $overrides),
        );

        $response->assertStatus(201);

        return WorkOrderChangeRequest::findOrFail($response->json('data.id'));
    }

    // ── Creating ──────────────────────────────────────────────────────────────

    public function test_supervisor_can_raise_a_change_request_with_impact_analysis(): void
    {
        $this->stopForChange();

        $response = $this->asSupervisor()->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/change-requests",
            [
                'title' => 'Increase planned quantity',
                'reason' => 'Customer extended the order.',
                'proposed' => ['planned_qty' => 150],
                'effective_from' => 'NEXT_BATCH',
            ],
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.status', ChangeRequestStatus::Draft->value)
            ->assertJsonPath('data.impact.produced_qty', 35)
            ->assertJsonPath('data.impact.remaining_qty', 65)
            ->assertJsonPath('data.requested_by_id', $this->supervisor->id);

        // The request is linked to the stop it came out of without the client saying so.
        $this->assertNotNull($response->json('data.work_order_stop_id'));
        $this->assertMatchesRegularExpression('#^CR/\d{4}/\d{4}$#', $response->json('data.code'));
    }

    public function test_change_request_requires_title_reason_and_a_proposal(): void
    {
        $this->asSupervisor()
            ->postJson("/api/v1/work-orders/{$this->workOrder->id}/change-requests", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'reason', 'proposed']);
    }

    public function test_change_request_rejects_fields_outside_the_allowlist(): void
    {
        // `status` is not changeable through a change request; with nothing else
        // proposed the request has no changes at all and must be refused.
        $this->asSupervisor()
            ->postJson("/api/v1/work-orders/{$this->workOrder->id}/change-requests", [
                'title' => 'Sneak a status change',
                'reason' => 'Should not work.',
                'proposed' => ['status' => 'DONE'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A change request must propose at least one change.');
    }

    public function test_change_request_rejects_an_unknown_product_revision(): void
    {
        $this->asSupervisor()
            ->postJson("/api/v1/work-orders/{$this->workOrder->id}/change-requests", [
                'title' => 'Bad revision',
                'reason' => 'Nope.',
                'proposed' => ['product_revision_id' => 999999],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proposed.product_revision_id']);
    }

    public function test_operator_cannot_raise_a_change_request(): void
    {
        $this->asOperator()
            ->postJson("/api/v1/work-orders/{$this->workOrder->id}/change-requests", [
                'title' => 'Nope',
                'reason' => 'Nope.',
                'proposed' => ['planned_qty' => 120],
            ])
            ->assertStatus(403);
    }

    public function test_guest_cannot_raise_a_change_request(): void
    {
        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/change-requests", [
            'title' => 'Nope',
            'reason' => 'Nope.',
            'proposed' => ['planned_qty' => 120],
        ])->assertStatus(401);
    }

    // ── Review workflow ───────────────────────────────────────────────────────

    public function test_full_workflow_from_draft_to_applied(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', ChangeRequestStatus::Submitted->value);

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', ChangeRequestStatus::Approved->value)
            ->assertJsonPath('data.approved_by_id', $this->supervisor->id);

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(200)
            ->assertJsonPath('data.status', ChangeRequestStatus::Applied->value)
            ->assertJsonPath('data.resulting_snapshot_version', 2);

        $this->assertEquals(150.0, (float) $this->workOrder->fresh()->planned_qty);
    }

    public function test_resume_finds_the_applied_change_itself_when_none_is_named(): void
    {
        // The review page posts the change request id, but the work-order lists post
        // an empty body. Both must release an order whose change has been applied.
        $this->stopForChange();
        $cr = $this->createRequest();

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(200);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume")
            ->assertStatus(200);

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
        $this->assertDatabaseHas('work_order_stops', [
            'work_order_id' => $this->workOrder->id,
            'applied_change_request_id' => $cr->id,
        ]);
    }

    public function test_an_applied_change_cannot_be_applied_a_second_time(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(200);

        // A second apply must not append another snapshot version or regenerate
        // steps again.
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(422);

        $this->assertSame(2, $this->workOrder->fresh()->snapshot_version);
        $this->assertSame(2, \App\Models\WorkOrderSnapshot::where('work_order_id', $this->workOrder->id)->count());
    }

    public function test_impact_does_not_blow_up_when_the_work_order_is_gone(): void
    {
        $cr = $this->createRequest();
        $this->workOrder->delete();

        // The policy refuses first, because viewing a change request requires being
        // able to view the order behind it. What matters here is that a deleted order
        // is answered, not turned into a 500.
        $this->asSupervisor()->getJson("/api/v1/work-order-change-requests/{$cr->id}/impact")
            ->assertStatus(403);

        // Behind the policy, the analysis itself refuses as a domain rule rather than
        // handing a null to analyzeImpact() as a TypeError.
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('The work order this change belongs to no longer exists.');

        app(\App\Services\WorkOrder\ChangeRequestService::class)->impactFor($cr->fresh());
    }

    public function test_a_draft_cannot_be_approved_without_review(): void
    {
        $cr = $this->createRequest();

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'A DRAFT change request cannot become APPROVED.');
    }

    public function test_an_unapproved_change_cannot_be_applied(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit")
            ->assertStatus(200);

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(422)
            ->assertJsonPath('message', 'A SUBMITTED change request cannot become APPLIED.');

        $this->assertEquals(100.0, (float) $this->workOrder->fresh()->planned_qty);
    }

    public function test_a_rejected_change_is_terminal_and_records_its_reason(): void
    {
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/reject", [
            'reason' => 'The customer has not confirmed in writing.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', ChangeRequestStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'The customer has not confirmed in writing.');

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve")
            ->assertStatus(422);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_a_submitted_request_can_no_longer_be_edited(): void
    {
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");

        $this->asSupervisor()->patchJson("/api/v1/work-order-change-requests/{$cr->id}", [
            'title' => 'Sneaky edit after review started',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Only a draft change request can be edited.');
    }

    public function test_a_draft_can_be_edited_and_its_impact_is_recomputed(): void
    {
        $cr = $this->createRequest();

        $this->asSupervisor()->patchJson("/api/v1/work-order-change-requests/{$cr->id}", [
            'title' => 'Corrected title',
            'proposed' => ['planned_qty' => 120],
        ])->assertStatus(200)
            ->assertJsonPath('data.title', 'Corrected title')
            ->assertJsonPath('data.proposed.planned_qty', 120);
    }

    public function test_operator_cannot_approve_a_change_request(): void
    {
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");

        $this->asOperator()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve")
            ->assertStatus(403);
    }

    public function test_a_user_without_the_approval_permission_cannot_apply(): void
    {
        // A planner who may raise and edit changes but was never granted the control
        // step — the split the policy exists for.
        $planner = User::factory()->create();
        $planner->givePermissionTo(['view work orders', 'edit work orders']);
        $this->assertFalse($planner->can('approve work order changes'));
        $token = $planner->createToken('test')->plainTextToken;

        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");

        $this->asUser($token)
            ->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(403);
    }

    // ── Applying: what it may and may not touch ───────────────────────────────

    public function test_apply_is_refused_while_the_order_is_running(): void
    {
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'A change can only be applied to a stopped work order, or to one that has not started production.'
            );
    }

    public function test_apply_refuses_a_quantity_below_what_was_already_produced(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest(['proposed' => ['planned_qty' => 10]]);
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(422)
            ->assertJsonPath('message', 'The planned quantity cannot be set below the quantity already produced.');

        $this->assertEquals(100.0, (float) $this->workOrder->fresh()->planned_qty);
    }

    public function test_immediate_is_refused_once_production_has_been_executed(): void
    {
        $batch = app(WorkOrderService::class)->createBatch($this->workOrder, 20);
        $batch->steps()->first()->update(['status' => BatchStep::STATUS_DONE]);

        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply", [
            'effective_from' => 'IMMEDIATE',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'IMMEDIATE is not allowed: production has already been executed under the current configuration. Use NEXT_BATCH or REMAINING_QUANTITY.']);
    }

    public function test_applying_a_change_never_rewrites_executed_work(): void
    {
        $service = app(WorkOrderService::class);

        // A finished batch and a completed step: the execution record that must
        // survive the change untouched.
        $done = $service->createBatch($this->workOrder, 35);
        $doneStep = $done->steps()->first();
        $doneStep->update(['status' => BatchStep::STATUS_DONE]);
        $done->update(['status' => Batch::STATUS_DONE, 'produced_qty' => 35]);

        $before = [
            'batch_status' => $done->fresh()->status,
            'batch_produced' => (float) $done->fresh()->produced_qty,
            'batch_version' => $done->fresh()->snapshot_version,
            'step_status' => $doneStep->fresh()->status,
            'step_name' => $doneStep->fresh()->name,
            'step_id' => $doneStep->id,
        ];

        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply", [
            'effective_from' => 'NEXT_BATCH',
        ])->assertStatus(200);

        $done->refresh();
        $this->assertSame($before['batch_status'], $done->status);
        $this->assertEquals($before['batch_produced'], (float) $done->produced_qty);
        $this->assertSame($before['batch_version'], $done->snapshot_version, 'A completed batch keeps the configuration it was built under.');

        $this->assertDatabaseHas('batch_steps', [
            'id' => $before['step_id'],
            'status' => $before['step_status'],
            'name' => $before['step_name'],
        ]);

        // Produced quantity is execution data and is never touched by a change.
        $this->assertEquals(35.0, (float) $this->workOrder->fresh()->produced_qty);
    }

    public function test_applying_appends_a_version_and_new_batches_carry_it(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(200);

        $this->workOrder->refresh();
        $this->assertSame(2, $this->workOrder->snapshot_version);

        // Version 1 (as released) is still on file next to version 2.
        $this->assertSame([1, 2], $this->workOrder->snapshots()->pluck('version')->all());
        $this->assertDatabaseHas('work_order_snapshots', [
            'work_order_id' => $this->workOrder->id,
            'version' => 2,
            'change_request_id' => $cr->id,
        ]);

        // Production after the change is attributable to the new configuration.
        $newBatch = app(WorkOrderService::class)->createBatch($this->workOrder->fresh(), 20);
        $this->assertSame(2, $newBatch->snapshot_version);
    }

    public function test_applying_records_the_before_state_as_a_diff(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply");

        $response = $this->asSupervisor()->getJson("/api/v1/work-order-change-requests/{$cr->id}");

        $response->assertStatus(200)
            ->assertJsonPath('diff.0.field', 'planned_qty')
            ->assertJsonPath('diff.0.to', 150);

        // The before-state is the value at apply time, not the current one.
        $this->assertEquals(100.0, (float) $response->json('diff.0.from'));
    }

    public function test_applying_records_the_remaining_material_requirements(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(200);

        $this->assertArrayHasKey('remaining_requirements', $cr->fresh()->impact);
    }

    public function test_a_change_cannot_be_applied_twice(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(200);

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(422);

        // Still one version, not two.
        $this->assertSame(2, $this->workOrder->fresh()->snapshot_version);
    }

    // ── Resume after an applied change ────────────────────────────────────────

    public function test_change_hold_resumes_once_the_change_has_been_applied(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(200);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume", [
            'change_request_id' => $cr->id,
            'notes' => 'Resumed on the new configuration.',
        ])->assertStatus(200);

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
        $this->assertDatabaseHas('work_order_stops', [
            'work_order_id' => $this->workOrder->id,
            'applied_change_request_id' => $cr->id,
            'resulting_status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_resume_refuses_a_change_request_that_was_only_approved(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume", [
            'change_request_id' => $cr->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'The referenced change request has not been applied yet.');
    }

    public function test_resume_refuses_a_change_request_from_another_work_order(): void
    {
        $this->stopForChange();

        $other = WorkOrder::factory()->create();
        $foreign = WorkOrderChangeRequest::factory()->create(['work_order_id' => $other->id]);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume", [
            'change_request_id' => $foreign->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['change_request_id']);
    }

    /**
     * A change applied for an earlier stop must not unlock a later change hold — the
     * order would restart on the old configuration with nothing having been changed.
     */
    public function test_a_change_applied_before_this_stop_does_not_release_the_hold(): void
    {
        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply");
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume", [
            'change_request_id' => $cr->id,
        ])->assertStatus(200);

        // A second hold, later. The old change is history, not a resolution.
        $this->travel(1)->days();
        $this->stopForChange();

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume", [
            'change_request_id' => $cr->id,
        ])->assertStatus(422)
            ->assertJsonPath(
                'message',
                'The referenced change request was applied before this stop and does not resolve it.'
            );

        $this->assertSame(WorkOrder::STATUS_CHANGE_HOLD, $this->workOrder->fresh()->status);
    }

    public function test_a_blocked_order_cannot_be_resumed_while_the_blocking_issue_is_open(): void
    {
        $issueType = \App\Models\IssueType::factory()->create(['is_blocking' => true]);
        \App\Models\Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $issueType->id,
            'status' => \App\Models\Issue::STATUS_OPEN,
        ]);
        $this->workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This work order is blocked by an open blocking issue. Resolve the issue to unblock it.'
            );

        $this->assertSame(WorkOrder::STATUS_BLOCKED, $this->workOrder->fresh()->status);
    }

    /**
     * A change raised before production starts must be appliable — otherwise it is
     * approved and then permanently stuck, because nothing can move a PENDING order
     * into a stopped state.
     */
    public function test_a_change_can_be_applied_to_an_order_that_has_not_started(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_PENDING, 'produced_qty' => 0]);

        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply")
            ->assertStatus(200)
            ->assertJsonPath('data.resulting_snapshot_version', 2);

        $this->assertEquals(150.0, (float) $this->workOrder->fresh()->planned_qty);
    }

    /** A step an operator skipped carries its own audit trail and must survive a change. */
    public function test_a_skipped_step_counts_as_executed_and_is_not_regenerated(): void
    {
        $batch = app(WorkOrderService::class)->createBatch($this->workOrder, 20);
        $step = $batch->steps()->first();
        $step->update([
            'status' => BatchStep::STATUS_SKIPPED,
            'skip_reason' => 'Fixture already fitted.',
            'completed_by_id' => $this->supervisor->id,
            'completed_at' => now(),
        ]);

        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");

        // IMMEDIATE would regenerate the batch, so it must be refused outright.
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply", [
            'effective_from' => 'IMMEDIATE',
        ])->assertStatus(422);

        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply", [
            'effective_from' => 'REMAINING_QUANTITY',
        ])->assertStatus(200);

        $this->assertDatabaseHas('batch_steps', [
            'id' => $step->id,
            'status' => BatchStep::STATUS_SKIPPED,
            'skip_reason' => 'Fixture already fitted.',
            'deleted_at' => null,
        ]);
    }

    /** Variant siblings are SKIPPED at generation; that is not execution. */
    public function test_a_freshly_generated_batch_is_still_regenerated(): void
    {
        $batch = app(WorkOrderService::class)->createBatch($this->workOrder, 20);
        $originalStepIds = $batch->steps()->pluck('id')->all();

        $this->stopForChange();
        $cr = $this->createRequest();
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/submit");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/approve");
        $this->asSupervisor()->postJson("/api/v1/work-order-change-requests/{$cr->id}/apply", [
            'effective_from' => 'REMAINING_QUANTITY',
        ])->assertStatus(200);

        $newStepIds = $batch->fresh()->steps()->pluck('id')->all();
        $this->assertNotEquals($originalStepIds, $newStepIds);
        // The old rows are soft-deleted with an audit stamp, not silently dropped.
        $this->assertSoftDeleted('batch_steps', ['id' => $originalStepIds[0]]);
    }

    public function test_the_diff_shows_a_before_value_for_the_bom_selection(): void
    {
        // The current selection lives in a pivot, not on the order — the case where
        // plain attribute access silently reports null.
        $current = \App\Models\ProcessTemplate::factory()->create([
            'product_type_id' => $this->workOrder->product_type_id,
            'version' => 10,
        ]);
        $replacement = \App\Models\ProcessTemplate::factory()->create([
            'product_type_id' => $this->workOrder->product_type_id,
            'version' => 11,
        ]);
        app(WorkOrderService::class)->syncBomSelection($this->workOrder, [$current->id]);

        $cr = $this->createRequest([
            'proposed' => ['bom_template_ids' => [$replacement->id]],
            'title' => 'Switch the BOM',
        ]);

        $response = $this->asSupervisor()->getJson("/api/v1/work-order-change-requests/{$cr->id}");

        $response->assertStatus(200)->assertJsonPath('diff.0.field', 'bom_template_ids');
        $this->assertSame([$current->id], $response->json('diff.0.from'));
        $this->assertSame([$replacement->id], $response->json('diff.0.to'));
    }

    // ── Reading ───────────────────────────────────────────────────────────────

    public function test_impact_endpoint_reports_the_current_picture(): void
    {
        $cr = $this->createRequest();

        $this->asSupervisor()->getJson("/api/v1/work-order-change-requests/{$cr->id}/impact")
            ->assertStatus(200)
            ->assertJsonPath('data.remaining_qty', 65)
            ->assertJsonStructure(['data' => ['batches', 'steps', 'materials', 'warnings']]);
    }

    public function test_impact_warns_when_the_proposal_conflicts_with_completed_work(): void
    {
        $cr = $this->createRequest(['proposed' => ['planned_qty' => 20]]);

        $warnings = $this->asSupervisor()
            ->getJson("/api/v1/work-order-change-requests/{$cr->id}/impact")
            ->json('data.warnings');

        $this->assertNotEmpty($warnings);
    }

    public function test_change_requests_are_listed_for_the_work_order(): void
    {
        $this->createRequest();
        $this->createRequest(['title' => 'Second change']);

        $this->asSupervisor()->getJson("/api/v1/work-orders/{$this->workOrder->id}/change-requests")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_operator_outside_the_line_cannot_read_a_change_request(): void
    {
        $other = WorkOrder::factory()->create();
        $foreign = WorkOrderChangeRequest::factory()->create(['work_order_id' => $other->id]);

        $this->asOperator()->getJson("/api/v1/work-order-change-requests/{$foreign->id}")
            ->assertStatus(403);
    }
}
