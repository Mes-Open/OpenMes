<?php

namespace Tests\Feature\Quality;

use App\Enums\IssueDisposition;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepTimeEntry;
use App\Models\Issue;
use App\Models\MaterialAllocation;
use App\Models\ReworkOperation;
use App\Models\ScrapEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Quality\ReworkService;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Executable rework routing (#400, item 2).
 */
class ReworkRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $supervisor;

    private WorkOrder $workOrder;

    private Batch $batch;

    private BatchStep $step1;

    private BatchStep $step2;

    private Workstation $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-24 08:00:00'));

        $this->workOrder = WorkOrder::factory()->create(['planned_qty' => 10]);
        $this->machine = Workstation::factory()->create(['line_id' => $this->workOrder->line_id, 'name' => 'Grinder']);
        $this->batch = app(WorkOrderService::class)->createBatch($this->workOrder, 10);

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operator->lines()->attach($this->workOrder->line_id);
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');

        $batchService = app(BatchService::class);
        $steps = $this->batch->steps()->orderBy('step_number')->get();
        $this->assertGreaterThanOrEqual(2, $steps->count());
        [$this->step1, $this->step2] = [$steps[0], $steps[1]];
        $this->step1->update(['workstation_id' => $this->machine->id]);

        // Step 1 done (10 good), step 2 running with the 10 pieces waiting at it.
        $batchService->startStep($this->step1->fresh(), $this->operator);
        $batchService->completeStep($this->step1->fresh(), $this->operator);
        $batchService->startStep($this->step2->fresh(), $this->operator);
        $this->step1->refresh();
        $this->step2->refresh();
    }

    private function issue(array $overrides = []): Issue
    {
        return Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'batch_step_id' => $this->step2->id,
            ...$overrides,
        ]);
    }

    private function api(User $user)
    {
        return $this->actingAs($user, 'sanctum');
    }

    private function send(Issue $issue, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->api($this->supervisor)->postJson("/api/v1/issues/{$issue->id}/rework-operations", [
            'quantity' => 3,
            'reason' => 'Burr on the edge',
            'target_batch_step_id' => $this->step1->id,
            ...$payload,
        ]);
    }

    public function test_supervisor_sends_pieces_back_to_an_earlier_operation(): void
    {
        $issue = $this->issue();

        $this->send($issue)
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.source_step.id', $this->step2->id)
            ->assertJsonPath('data.target_step.id', $this->step1->id);

        $this->assertSame(IssueDisposition::Rework->value, $issue->fresh()->disposition);
        $this->assertSame($this->supervisor->id, $issue->fresh()->disposition_by_id);
        // Held at the step where the defect was found.
        $this->assertEquals(3, $this->step2->fresh()->rework_held_qty);
        $this->assertEquals(7, $this->step2->fresh()->availableQty());
    }

    public function test_held_pieces_block_the_step_until_the_rework_ends(): void
    {
        $this->send($this->issue())->assertCreated();

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$this->step2->id}/complete")
            ->assertStatus(422)
            ->assertJsonPath('errors.step.0', '3 pieces are held in rework at this step — finish or cancel the rework first.');
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->step2->fresh()->status);
    }

    public function test_accepted_rework_returns_the_pieces_without_counting_them_twice(): void
    {
        $id = $this->send($this->issue())->json('data.id');
        $allocationsBefore = MaterialAllocation::count();

        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/start")->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/complete", ['accepted_qty' => 3, 'scrapped_qty' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.accepted_qty', 3);

        // The earlier operation's record is untouched; no material was allocated again.
        $step1 = $this->step1->fresh();
        $this->assertEquals(10, $step1->passed_qty);
        $this->assertSame(BatchStep::STATUS_DONE, $step1->status);
        $this->assertSame($allocationsBefore, MaterialAllocation::count());

        // The pieces are back at step 2 and it can complete with all 10.
        $this->assertEquals(0, $this->step2->fresh()->rework_held_qty);
        $this->assertEquals(10, $this->step2->fresh()->availableQty());
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$this->step2->id}/complete")->assertOk();
        $this->assertEquals(10, $this->step2->fresh()->passed_qty);
    }

    public function test_scrapped_rework_books_scrap_at_the_source_step(): void
    {
        $id = $this->send($this->issue())->json('data.id');
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/start");

        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/complete", ['accepted_qty' => 2, 'scrapped_qty' => 1, 'notes' => 'Crack'])
            ->assertOk()
            ->assertJsonPath('data.scrapped_qty', 1);

        $step2 = $this->step2->fresh();
        $this->assertEquals(1, $step2->scrap_qty);
        $this->assertEquals(0, $step2->rework_held_qty);
        $this->assertEquals(9, $step2->availableQty());
        $scrap = ScrapEntry::where('batch_step_id', $step2->id)->sole();
        $this->assertEquals(1, $scrap->quantity);
        $this->assertSame($scrap->id, ReworkOperation::find($id)->scrap_entry_id);
    }

    public function test_cancelled_rework_releases_the_pieces_unchanged(): void
    {
        $id = $this->send($this->issue())->json('data.id');

        $this->api($this->supervisor)->postJson("/api/v1/rework-operations/{$id}/cancel", ['reason' => 'Defect was within tolerance'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals(0, $this->step2->fresh()->rework_held_qty);
        $this->assertEquals(10, $this->step2->fresh()->availableQty());
        $this->api($this->supervisor)->postJson("/api/v1/rework-operations/{$id}/cancel", ['reason' => 'again'])->assertStatus(422);
    }

    public function test_rework_time_is_recorded_on_the_rework_not_on_the_original_operation(): void
    {
        $id = $this->send($this->issue())->json('data.id');

        // Not before the rework is started.
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/time/labor/start")
            ->assertStatus(422)->assertJsonPath('errors.rework.0', 'Time can only be recorded on a rework in progress.');

        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/start");
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/time/labor/start")->assertOk();
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/time/machine/start")->assertOk();
        $this->travel(20)->minutes();
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/time/labor/stop")
            ->assertOk()->assertJsonPath('data.live_totals.machine_minutes', 20);
        $this->travel(10)->minutes();
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/complete", ['accepted_qty' => 3, 'scrapped_qty' => 0])->assertOk();

        $rework = ReworkOperation::find($id);
        $this->assertSame(20, $rework->actual_labor_minutes);
        $this->assertSame(30, $rework->actual_machine_minutes);
        $this->assertSame(0, BatchStepTimeEntry::open()->count());
        $this->assertTrue(BatchStepTimeEntry::where('rework_operation_id', $id)->where('batch_step_id', $this->step1->id)->exists());
        // The original operation's time is untouched.
        $this->assertNull($this->step1->fresh()->actual_labor_minutes);
        $this->assertNull($this->step1->fresh()->actual_machine_minutes);
    }

    public function test_an_operator_is_never_on_a_step_and_a_rework_at_once(): void
    {
        $id = $this->send($this->issue())->json('data.id');
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/start");

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$this->step2->id}/time-entries/labor/start")->assertCreated();
        $this->travel(5)->minutes();
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/time/labor/start")->assertOk();

        $this->assertSame(1, BatchStepTimeEntry::open()->where('kind', 'labor')->count());
        $this->assertSame(5, $this->step2->fresh()->actual_labor_minutes);
    }

    public function test_only_pieces_waiting_at_the_step_can_be_sent(): void
    {
        $this->send($this->issue(), ['quantity' => 11])
            ->assertStatus(422)->assertJsonPath('errors.rework.0', 'Only 10 pieces are waiting at this step.');

        $this->send($this->issue(), ['quantity' => 6])->assertCreated();
        $this->send($this->issue(), ['quantity' => 5])
            ->assertStatus(422)->assertJsonPath('errors.rework.0', 'Only 4 pieces are waiting at this step.');
    }

    public function test_rework_cannot_exceed_the_non_conforming_quantity(): void
    {
        $issue = $this->issue(['non_conforming_qty' => 4]);

        $this->send($issue, ['quantity' => 3])->assertCreated();
        $this->send($issue, ['quantity' => 2])
            ->assertStatus(422)->assertJsonPath('errors.rework.0', 'Rework cannot exceed the non-conforming quantity of the issue (4).');
    }

    public function test_rework_goes_back_not_forward_and_stays_in_the_batch(): void
    {
        $issue = $this->issue(['batch_step_id' => $this->step1->id]);
        $this->send($issue, ['target_batch_step_id' => $this->step2->id])
            ->assertStatus(422)->assertJsonPath('errors.rework.0', 'Rework goes back to the step where the defect was found or an earlier one.');

        $otherBatch = app(WorkOrderService::class)->createBatch($this->workOrder, 5);
        $this->send($this->issue(), ['target_batch_step_id' => $otherBatch->steps()->first()->id])
            ->assertStatus(422)->assertJsonPath('errors.rework.0', 'The rework operation must belong to the same batch.');
    }

    public function test_an_issue_with_another_disposition_cannot_be_reworked(): void
    {
        $this->send($this->issue(['disposition' => IssueDisposition::Scrap->value]))
            ->assertStatus(422)
            ->assertJsonPath('errors.rework.0', 'This issue already has another disposition. Only a rework disposition can be sent to rework.');
    }

    public function test_completion_quantities_must_add_up(): void
    {
        $id = $this->send($this->issue())->json('data.id');

        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/complete", ['accepted_qty' => 3, 'scrapped_qty' => 0])
            ->assertStatus(422)->assertJsonPath('errors.rework.0', 'Start the rework before completing it.');

        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/start");
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/complete", ['accepted_qty' => 2, 'scrapped_qty' => 0])
            ->assertStatus(422)->assertJsonPath('errors.rework.0', 'Accepted and scrapped pieces must add up to 3.');
    }

    public function test_requests_are_validated(): void
    {
        $issue = $this->issue();
        $this->api($this->supervisor)->postJson("/api/v1/issues/{$issue->id}/rework-operations", [])
            ->assertStatus(422)->assertJsonValidationErrors(['quantity', 'reason', 'target_batch_step_id']);
        $this->send($issue, ['quantity' => 0, 'target_batch_step_id' => 999999])
            ->assertStatus(422)->assertJsonValidationErrors(['quantity', 'target_batch_step_id']);

        $id = $this->send($issue)->json('data.id');
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/complete", ['accepted_qty' => -1])
            ->assertStatus(422)->assertJsonValidationErrors(['accepted_qty', 'scrapped_qty']);
        $this->api($this->supervisor)->postJson("/api/v1/rework-operations/{$id}/cancel", [])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_authorization(): void
    {
        $issue = $this->issue();
        $id = $this->send($issue)->json('data.id');

        // Guests
        $this->app['auth']->forgetGuards();
        $this->postJson("/api/v1/issues/{$issue->id}/rework-operations", [])->assertUnauthorized();
        $this->getJson("/api/v1/rework-operations/{$id}")->assertUnauthorized();

        // Operators run rework but neither send nor cancel it.
        $this->api($this->operator)->postJson("/api/v1/issues/{$issue->id}/rework-operations", ['quantity' => 1, 'reason' => 'x', 'target_batch_step_id' => $this->step1->id])
            ->assertForbidden();
        $this->api($this->operator)->postJson("/api/v1/rework-operations/{$id}/cancel", ['reason' => 'x'])->assertForbidden();
        $this->api($this->operator)->getJson("/api/v1/rework-operations/{$id}")->assertOk();

        // An operator of another line sees nothing.
        $stranger = User::factory()->create();
        $stranger->assignRole('Operator');
        $this->api($stranger)->getJson("/api/v1/rework-operations/{$id}")->assertForbidden();
        $this->api($stranger)->postJson("/api/v1/rework-operations/{$id}/start")->assertForbidden();
        $this->api($stranger)->getJson('/api/v1/rework-operations')->assertOk()->assertJsonCount(0, 'data');
        $this->api($this->operator)->getJson("/api/v1/rework-operations?status=open&work_order_id={$this->workOrder->id}")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_transfer_flow_holds_pieces_from_the_next_station(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $id = $this->send($this->issue(), ['quantity' => 4])->json('data.id');
        $batchService = app(BatchService::class);

        // Only the 6 pieces not held may leave step 2.
        $this->expectExceptionMessage('Only 6 pieces are waiting at this step.');
        try {
            $batchService->recordQuantity($this->step2->fresh(), $this->operator, 7);
        } finally {
            $batchService->recordQuantity($this->step2->fresh(), $this->operator, 6);
            $this->assertEquals(6, $this->step2->fresh()->passed_qty);
            $this->assertNotNull($this->step2->fresh()->completionBlocker());

            app(ReworkService::class)->start(ReworkOperation::find($id), $this->operator);
            app(ReworkService::class)->complete(ReworkOperation::find($id), 4, 0, $this->operator);
            $this->assertEquals(4, $this->step2->fresh()->availableQty());
        }
    }

    public function test_deleting_the_step_soft_deletes_its_rework(): void
    {
        $id = $this->send($this->issue())->json('data.id');

        $this->step2->fresh()->delete();

        $this->assertSoftDeleted('rework_operations', ['id' => $id]);
    }

    public function test_work_order_lists_its_rework(): void
    {
        $this->send($this->issue())->assertCreated();

        $this->api($this->operator)->getJson("/api/v1/work-orders/{$this->workOrder->id}/rework-operations")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reason', 'Burr on the edge');
    }

    public function test_issues_page_sends_and_cancels_rework(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $issue = $this->issue();

        $this->actingAs($admin)->getJson("/admin/issues/{$issue->id}/rework")
            ->assertOk()
            ->assertJsonPath('reworks', [])
            ->assertJsonFragment(['id' => $this->step2->id, 'available_qty' => 10]);

        $this->actingAs($admin)->postJson("/admin/issues/{$issue->id}/rework", [
            'source_batch_step_id' => $this->step2->id,
            'target_batch_step_id' => $this->step1->id,
            'quantity' => 2,
            'reason' => 'Scratch',
        ])->assertCreated()->assertJsonPath('reworks.0.status', 'open');

        $this->actingAs($admin)->postJson("/admin/issues/{$issue->id}/rework", ['target_batch_step_id' => $this->step1->id, 'quantity' => 50, 'reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('errors.rework.0', 'Only 8 pieces are waiting at this step.');

        $id = ReworkOperation::sole()->id;
        $this->actingAs($admin)->postJson("/admin/rework-operations/{$id}/cancel", ['reason' => 'Not needed'])
            ->assertOk()->assertJsonPath('reworks.0.status', 'cancelled');
    }

    public function test_operator_panel_runs_the_rework(): void
    {
        $id = app(ReworkService::class)->create($this->issue(), [
            'quantity' => 3, 'reason' => 'Burr on the edge', 'target_batch_step_id' => $this->step1->id,
        ], $this->supervisor)->id;
        $session = ['selected_line_id' => $this->workOrder->line_id];

        $panel = $this->actingAs($this->operator)->withSession($session)->getJson("/operator/work-order/{$this->workOrder->id}/panel")->assertOk();
        $this->assertSame($id, $panel->json('reworks.0.id'));
        $this->assertSame('open', $panel->json('reworks.0.status'));
        $step2 = collect($panel->json('steps'))->firstWhere('id', $this->step2->id);
        $this->assertSame('3 pieces are held in rework at this step — finish or cancel the rework first.', $step2['completion_blocker']);

        $this->actingAs($this->operator)->withSession($session)->post("/operator/rework/{$id}/start")->assertSessionHas('success', 'Rework started.');
        $this->actingAs($this->operator)->withSession($session)->post("/operator/rework/{$id}/time/labor/start")->assertSessionHas('success', 'Clocked on.');
        $this->travel(12)->minutes();

        $panel = $this->actingAs($this->operator)->withSession($session)->getJson("/operator/work-order/{$this->workOrder->id}/panel");
        $this->assertSame(12, $panel->json('reworks.0.time.labor_minutes'));
        $this->assertTrue($panel->json('reworks.0.time.clocked_on'));
        // The step's own time does not show the rework's.
        $this->assertFalse(collect($panel->json('steps'))->firstWhere('id', $this->step1->id)['time']['clocked_on']);

        $this->actingAs($this->operator)->withSession($session)->post("/operator/rework/{$id}/complete", ['accepted_qty' => 3, 'scrapped_qty' => 0])
            ->assertSessionHas('success', 'Rework completed.');
        $this->assertSame(12, ReworkOperation::find($id)->actual_labor_minutes);
        $this->assertSame([], $this->actingAs($this->operator)->withSession($session)->getJson("/operator/work-order/{$this->workOrder->id}/panel")->json('reworks'));

        // Another line's terminal is refused.
        $this->actingAs($this->operator)->withSession(['selected_line_id' => 999999])->post("/operator/rework/{$id}/start")->assertSessionHas('error');
    }
}
