<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStop;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Structured production stops and controlled resume (#182).
 */
class WorkOrderStopApiTest extends TestCase
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

    private function asSupervisor(): self
    {
        return $this->withHeader('Authorization', "Bearer {$this->supervisorToken}");
    }

    // ── POST /api/v1/work-orders/{workOrder}/stop ─────────────────────────────

    public function test_supervisor_can_stop_an_in_progress_work_order(): void
    {
        $response = $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'MACHINE_FAILURE',
            'reason' => 'Spindle bearing failed.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'MACHINE_FAILURE')
            ->assertJsonPath('data.requires_change', false);

        $this->assertDatabaseHas('work_order_stops', [
            'work_order_id' => $this->workOrder->id,
            'type' => 'MACHINE_FAILURE',
            'stopped_by_id' => $this->supervisor->id,
        ]);

        // A stop that needs no configuration change is an ordinary pause.
        $this->assertSame(WorkOrder::STATUS_PAUSED, $this->workOrder->fresh()->status);
    }

    public function test_stop_requiring_a_change_puts_the_order_on_change_hold(): void
    {
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'ENGINEERING_CHANGE',
            'reason' => 'Hole diameter must change before continuing.',
            'requires_change' => true,
        ])->assertStatus(201);

        $this->assertSame(WorkOrder::STATUS_CHANGE_HOLD, $this->workOrder->fresh()->status);
    }

    public function test_stop_photographs_the_state_at_the_moment_it_was_raised(): void
    {
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'QUALITY_HOLD',
            'reason' => 'Dimensional deviation found.',
        ])->assertStatus(201);

        $stop = WorkOrderStop::where('work_order_id', $this->workOrder->id)->firstOrFail();

        $this->assertEquals(35.0, (float) $stop->produced_qty_at_stop);
        $this->assertSame(1, $stop->snapshot_version_at_stop);
        $this->assertEquals(65.0, $stop->context['remaining_qty']);

        // Producing more after the stop must not move the photograph.
        $this->workOrder->update(['produced_qty' => 40]);
        $this->assertEquals(35.0, (float) $stop->fresh()->produced_qty_at_stop);
    }

    public function test_cannot_stop_a_work_order_that_is_not_in_progress(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_PENDING]);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'OPERATIONAL',
            'reason' => 'Shift end.',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Only IN_PROGRESS work orders can be stopped.');
    }

    public function test_cannot_open_a_second_stop_while_one_is_open(): void
    {
        $payload = ['type' => 'OPERATIONAL', 'reason' => 'Waiting for the forklift.'];

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", $payload)
            ->assertStatus(201);

        // The order is PAUSED now, so this fails on the status guard first; force it
        // back to IN_PROGRESS to reach the "already stopped" guard itself. Refresh
        // first, or the stale in-memory IN_PROGRESS makes the update a no-op.
        $this->workOrder->refresh()->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'This work order already has an open production stop.');
    }

    public function test_stop_requires_a_type_and_a_reason(): void
    {
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'reason']);
    }

    public function test_stop_rejects_an_unknown_type(): void
    {
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'COFFEE_BREAK',
            'reason' => 'No.',
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_stop_rejects_a_batch_from_another_work_order(): void
    {
        $other = WorkOrder::factory()->create();
        $foreignBatch = app(WorkOrderService::class)->createBatch($other, 10);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'OPERATIONAL',
            'reason' => 'Wrong batch.',
            'batch_id' => $foreignBatch->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['batch_id']);
    }

    public function test_stop_rejects_an_issue_from_another_work_order(): void
    {
        $foreignIssue = \App\Models\Issue::factory()->create([
            'work_order_id' => WorkOrder::factory()->create()->id,
        ]);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'QUALITY_HOLD',
            'reason' => 'Wrong issue.',
            'issue_id' => $foreignIssue->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['issue_id']);
    }

    public function test_stop_opens_a_linked_downtime_record(): void
    {
        $reason = \App\Models\DowntimeReason::factory()->create();

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'MACHINE_FAILURE',
            'reason' => 'Spindle failed.',
            'downtime_reason_id' => $reason->id,
        ])->assertStatus(201);

        $stop = WorkOrderStop::where('work_order_id', $this->workOrder->id)->firstOrFail();

        $this->assertNotNull($stop->production_downtime_id);
        $this->assertDatabaseHas('production_downtimes', [
            'id' => $stop->production_downtime_id,
            'line_id' => $this->workOrder->line_id,
            'downtime_reason_id' => $reason->id,
        ]);
    }

    public function test_a_requested_downtime_is_refused_rather_than_dropped_when_the_order_has_no_line(): void
    {
        // A downtime is booked against a line. Rather than saving the stop and
        // silently losing the downtime, the whole request is refused.
        $this->workOrder->update(['line_id' => null]);
        $reason = \App\Models\DowntimeReason::factory()->create();

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'MACHINE_FAILURE',
            'reason' => 'Spindle failed.',
            'downtime_reason_id' => $reason->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('work_order_stops', 0);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
    }

    public function test_guest_cannot_stop_a_work_order(): void
    {
        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'OPERATIONAL',
            'reason' => 'Nope.',
        ])->assertStatus(401);
    }

    public function test_operator_cannot_stop_a_work_order(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
            ->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
                'type' => 'OPERATIONAL',
                'reason' => 'Nope.',
            ])->assertStatus(403);
    }

    // ── POST /api/v1/work-orders/{workOrder}/resume ───────────────────────────

    public function test_resume_closes_the_stop_and_records_its_duration(): void
    {
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'MATERIAL_SHORTAGE',
            'reason' => 'Waiting for steel.',
        ])->assertStatus(201);

        $stop = WorkOrderStop::where('work_order_id', $this->workOrder->id)->firstOrFail();
        $stop->update(['stopped_at' => now()->subMinutes(45)]);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume", [
            'notes' => 'Steel delivered.',
        ])->assertStatus(200);

        $stop->refresh();
        $this->assertNotNull($stop->resumed_at);
        $this->assertSame($this->supervisor->id, $stop->resumed_by_id);
        $this->assertSame(45, $stop->duration_minutes);
        $this->assertSame('Steel delivered.', $stop->resume_notes);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
    }

    public function test_change_hold_cannot_be_resumed_without_an_applied_change(): void
    {
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/stop", [
            'type' => 'ENGINEERING_CHANGE',
            'reason' => 'Revision must change.',
            'requires_change' => true,
        ])->assertStatus(201);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This work order is on change hold: an approved change request must be applied before resuming.'
            );

        $this->assertSame(WorkOrder::STATUS_CHANGE_HOLD, $this->workOrder->fresh()->status);
    }

    public function test_legacy_pause_still_resumes_without_a_stop_record(): void
    {
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/pause")
            ->assertStatus(200);

        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume")
            ->assertStatus(200);

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
        $this->assertDatabaseCount('work_order_stops', 0);
    }

    public function test_cannot_resume_a_work_order_that_is_not_stopped(): void
    {
        $this->asSupervisor()->postJson("/api/v1/work-orders/{$this->workOrder->id}/resume")
            ->assertStatus(422);
    }

    // ── GET /api/v1/work-orders/{workOrder}/stops ─────────────────────────────

    public function test_stop_history_is_listed_newest_first_with_durations(): void
    {
        WorkOrderStop::factory()->resumed()->create([
            'work_order_id' => $this->workOrder->id,
            'stopped_at' => now()->subHours(5),
            'reason' => 'Older stop',
        ]);
        WorkOrderStop::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'stopped_at' => now()->subHour(),
            'reason' => 'Newer stop',
        ]);

        $response = $this->asSupervisor()->getJson("/api/v1/work-orders/{$this->workOrder->id}/stops");

        $response->assertStatus(200);
        $reasons = array_column($response->json('data'), 'reason');
        $this->assertSame(['Newer stop', 'Older stop'], $reasons);

        // The open stop reports a running duration rather than null.
        $this->assertGreaterThanOrEqual(59, $response->json('data.0.duration_minutes_current'));
    }

    public function test_operator_outside_the_line_cannot_read_the_stop_history(): void
    {
        $other = WorkOrder::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
            ->getJson("/api/v1/work-orders/{$other->id}/stops")
            ->assertStatus(403);
    }
}
