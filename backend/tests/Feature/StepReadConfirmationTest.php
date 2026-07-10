<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Read-confirmation control: a step flagged `requires_confirmation` (its
 * instructions are critical) cannot be completed until the operator explicitly
 * acknowledges reading them; the acknowledgement records who and when. Steps not
 * so flagged are unaffected.
 */
class StepReadConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private BatchService $service;

    private Line $line;

    private WorkOrder $workOrder;

    private Batch $batch;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->service = app(BatchService::class);
        $this->line = Line::factory()->create();
        $this->workOrder = WorkOrder::factory()->create(['line_id' => $this->line->id]);
        $this->batch = app(WorkOrderService::class)->createBatch($this->workOrder, 50);

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operator->lines()->attach($this->line);
    }

    /** First step of the batch, forced IN_PROGRESS, optionally flagged critical. */
    private function inProgressStep(bool $requiresConfirmation = false): BatchStep
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();
        $step->update([
            'status' => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(10),
            'requires_confirmation' => $requiresConfirmation,
        ]);

        return $step->fresh();
    }

    public function test_critical_step_cannot_be_completed_without_confirmation(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);

        $this->expectException(\Exception::class);

        try {
            $this->service->completeStep($step, $this->operator);
        } finally {
            // The step must remain IN_PROGRESS - it did not pass the gate.
            $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $step->fresh()->status);
        }
    }

    public function test_non_critical_step_completes_without_confirmation(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: false);

        $this->service->completeStep($step, $this->operator);

        $this->assertSame(BatchStep::STATUS_DONE, $step->fresh()->status);
    }

    public function test_critical_step_completes_once_confirmed(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);

        $step->markReadConfirmed($this->operator);

        $this->service->completeStep($step->fresh(), $this->operator);

        $this->assertSame(BatchStep::STATUS_DONE, $step->fresh()->status);
    }

    public function test_confirmation_records_who_and_when(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);

        $step->markReadConfirmed($this->operator);

        $fresh = $step->fresh();
        $this->assertNotNull($fresh->confirmed_at);
        $this->assertSame($this->operator->id, $fresh->confirmed_by);
        $this->assertTrue($fresh->isReadConfirmed());
    }

    public function test_confirmation_is_idempotent_and_keeps_the_first_acknowledger(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);
        $other = User::factory()->create();

        $step->markReadConfirmed($this->operator);
        $firstAt = $step->fresh()->confirmed_at;

        // A second acknowledgement must not overwrite the recorded who/when.
        $step->fresh()->markReadConfirmed($other);

        $fresh = $step->fresh();
        $this->assertSame($this->operator->id, $fresh->confirmed_by);
        $this->assertEquals($firstAt->timestamp, $fresh->confirmed_at->timestamp);
    }

    public function test_operator_confirms_via_web_route_then_completes(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);

        // Blocked while unconfirmed: completing flashes an error, step stays put.
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.complete', $step))
            ->assertSessionHas('error');
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $step->fresh()->status);

        // Acknowledge the instructions.
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.confirm-instructions', $step))
            ->assertSessionHas('success');
        $this->assertSame($this->operator->id, $step->fresh()->confirmed_by);

        // Now completion goes through.
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.complete', $step))
            ->assertSessionHas('success');
        $this->assertSame(BatchStep::STATUS_DONE, $step->fresh()->status);
    }

    public function test_confirm_rejects_a_step_from_another_line(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id + 999])
            ->post(route('operator.batch-step.confirm-instructions', $step))
            ->assertSessionHas('error');
        $this->assertNull($step->fresh()->confirmed_at);
    }

    public function test_confirm_rejected_when_step_does_not_require_it(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: false);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.confirm-instructions', $step))
            ->assertSessionHas('error');
        $this->assertNull($step->fresh()->confirmed_at);
    }

    public function test_guest_cannot_confirm_instructions(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);

        // No authenticated user -> the `auth` middleware bounces to login.
        $this->post(route('operator.batch-step.confirm-instructions', $step))
            ->assertRedirect(route('login'));
        $this->assertNull($step->fresh()->confirmed_at);
    }

    public function test_user_without_production_role_cannot_confirm_instructions(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);
        // Authenticated but lacking Operator|Supervisor|Admin -> role middleware 403.
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.confirm-instructions', $step))
            ->assertForbidden();
        $this->assertNull($step->fresh()->confirmed_at);
    }

    public function test_api_operator_confirms_via_api_then_completes(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);

        // Blocked while unconfirmed: the shared gate rejects completion.
        $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batch-steps/{$step->id}/complete")
            ->assertStatus(422);
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $step->fresh()->status);

        // Acknowledge via the API.
        $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batch-steps/{$step->id}/confirm-instructions")
            ->assertOk();
        $this->assertSame($this->operator->id, $step->fresh()->confirmed_by);

        // Now completion goes through.
        $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batch-steps/{$step->id}/complete")
            ->assertOk();
        $this->assertSame(BatchStep::STATUS_DONE, $step->fresh()->status);
    }

    public function test_api_confirm_is_forbidden_for_an_unrelated_user(): void
    {
        // An operator not assigned to the work order's line cannot clear the gate
        // by step id (the `view` policy scopes operators to their lines).
        $step = $this->inProgressStep(requiresConfirmation: true);
        $outsider = User::factory()->create();
        $outsider->assignRole('Operator');

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/batch-steps/{$step->id}/confirm-instructions")
            ->assertForbidden();
        $this->assertNull($step->fresh()->confirmed_at);
    }

    public function test_api_guest_cannot_confirm_instructions(): void
    {
        $step = $this->inProgressStep(requiresConfirmation: true);

        $this->postJson("/api/v1/batch-steps/{$step->id}/confirm-instructions")
            ->assertUnauthorized();
        $this->assertNull($step->fresh()->confirmed_at);
    }

    public function test_requires_confirmation_flag_propagates_from_template_through_snapshot_to_batch_step(): void
    {
        // A template whose first step is flagged critical, the second is not.
        $template = \App\Models\ProcessTemplate::factory()->withSteps(2)->create();
        $template->steps()->orderBy('step_number')->first()->update(['requires_confirmation' => true]);

        $workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'process_snapshot' => $template->fresh('steps')->toSnapshot(),
        ]);
        $batch = app(WorkOrderService::class)->createBatch($workOrder, 10);

        $steps = $batch->steps()->orderBy('step_number')->get();
        $this->assertTrue($steps->first()->requires_confirmation, 'Critical template step must flag its batch step.');
        $this->assertFalse($steps->last()->requires_confirmation, 'Non-critical step must stay unflagged.');
    }
}
