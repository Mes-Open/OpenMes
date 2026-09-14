<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The operator Workstation view follows the workstation selected in the Queue
 * view (?workstation= / session): only work orders whose current step runs
 * there, and only that workstation's machine state.
 */
class WorkstationViewSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    private Workstation $exposure;

    private Workstation $pretest;

    private WorkOrder $atExposure;

    private WorkOrder $atPretest;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $this->line = Line::factory()->create();
        $this->exposure = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'FT-2 Exposure']);
        $this->pretest = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'FT-1 Pretest']);

        $this->atExposure = $this->workOrderAt($this->exposure);
        $this->atPretest = $this->workOrderAt($this->pretest);

        $this->setTrackingMode('per_operation');
    }

    private function workOrderAt(Workstation $workstation): WorkOrder
    {
        $wo = WorkOrder::factory()->create(['line_id' => $this->line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        BatchStep::factory()->done()->create(['batch_id' => $batch->id, 'step_number' => 1]);
        BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 2,
            'status' => BatchStep::STATUS_READY,
            'workstation_id' => $workstation->id,
        ]);

        return $wo;
    }

    private function setTrackingMode(string $mode): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'production_tracking_mode'],
            ['value' => json_encode($mode)]
        );
    }

    private function workstationView(string $query = '')
    {
        return $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->get('/operator/workstation'.$query);
    }

    public function test_without_a_selection_the_whole_line_is_shown(): void
    {
        $this->workstationView()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('operator/Workstation')
                ->where('selectedWorkstation', null)
                ->has('workOrders', 2)
                ->has('machineStates', 2)
            );
    }

    public function test_workstation_account_is_not_filtered_by_its_assignment_alone(): void
    {
        $account = User::factory()->create(['account_type' => 'workstation', 'workstation_id' => $this->exposure->id]);
        $account->assignRole('Operator');

        $this->actingAs($account)
            ->withSession(['selected_line_id' => $this->line->id])
            ->get('/operator/workstation')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selectedWorkstation', null)
                ->has('workOrders', 2)
            );

        // An actual selection still filters it.
        $this->get("/operator/workstation?workstation={$this->exposure->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selectedWorkstation.id', $this->exposure->id)
                ->has('workOrders', 1)
            );
    }

    public function test_selected_workstation_filters_orders_and_machine_states(): void
    {
        $this->workstationView("?workstation={$this->exposure->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selectedWorkstation.id', $this->exposure->id)
                ->has('workOrders', 1)
                ->where('workOrders.0.id', $this->atExposure->id)
                ->missing('workOrders.0.batches')
                ->has('machineStates', 1)
                ->where('machineStates.0.id', $this->exposure->id)
            );
    }

    /** A work order with no batch yet, routed through the given steps. */
    private function notStartedOrder(array $steps, string $status = WorkOrder::STATUS_PENDING): WorkOrder
    {
        return WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'status' => $status,
            'process_snapshot' => ['steps' => $steps],
        ]);
    }

    private function snapshotStep(int $number, ?Workstation $workstation, ?string $variantGroup = null): array
    {
        return [
            'step_number' => $number,
            'name' => "Step {$number}",
            'workstation_id' => $workstation?->id,
            'variant_group' => $variantGroup,
        ];
    }

    private function orderIdsAt(Workstation $workstation): array
    {
        $ids = [];
        $this->workstationView("?workstation={$workstation->id}")
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['workOrders'])->pluck('id')->sort()->values()->all();
            });

        return $ids;
    }

    public function test_not_started_order_whose_first_step_is_at_the_workstation_is_shown(): void
    {
        // Listed out of order on purpose: the first step is the lowest step_number.
        $startsHere = $this->notStartedOrder([
            $this->snapshotStep(2, $this->pretest),
            $this->snapshotStep(1, $this->exposure),
        ]);
        $passesLater = $this->notStartedOrder([
            $this->snapshotStep(1, $this->pretest),
            $this->snapshotStep(2, $this->exposure),
        ]);

        $this->assertSame([$this->atExposure->id, $startsHere->id], $this->orderIdsAt($this->exposure));
        $this->assertNotContains($passesLater->id, $this->orderIdsAt($this->exposure));
        $this->assertContains($passesLater->id, $this->orderIdsAt($this->pretest));
    }

    public function test_any_variant_of_a_first_step_group_counts(): void
    {
        $variant = $this->notStartedOrder([
            $this->snapshotStep(1, $this->pretest, 'start'),
            $this->snapshotStep(2, $this->exposure, 'start'),
            $this->snapshotStep(3, null),
        ]);

        $this->assertContains($variant->id, $this->orderIdsAt($this->exposure));
        $this->assertContains($variant->id, $this->orderIdsAt($this->pretest));
    }

    public function test_order_with_only_cancelled_batches_counts_as_not_started(): void
    {
        $restarted = $this->notStartedOrder([$this->snapshotStep(1, $this->exposure)], WorkOrder::STATUS_ACCEPTED);
        Batch::factory()->create(['work_order_id' => $restarted->id, 'status' => Batch::STATUS_CANCELLED]);

        $this->assertContains($restarted->id, $this->orderIdsAt($this->exposure));
    }

    public function test_finished_or_unrouted_orders_without_batches_are_not_shown(): void
    {
        $done = $this->notStartedOrder([$this->snapshotStep(1, $this->exposure)], WorkOrder::STATUS_DONE);
        $unrouted = $this->notStartedOrder([]);

        $ids = $this->orderIdsAt($this->exposure);
        $this->assertNotContains($done->id, $ids);
        $this->assertNotContains($unrouted->id, $ids);
    }

    public function test_component_specification_is_not_offered_as_a_column(): void
    {
        $this->atExposure->update(['extra_data' => [
            'component_specification' => ['material_code' => 'EX-FRAME'],
            'customer_ref' => 'PO-7',
        ]]);

        $this->workstationView()
            ->assertInertia(function (AssertableInertia $page) {
                $keys = collect($page->toArray()['props']['allColumns'])->pluck('key');
                $this->assertContains('customer_ref', $keys);
                $this->assertNotContains('component_specification', $keys);
            });
    }

    public function test_queue_lists_not_started_orders_separately_from_ready_ones(): void
    {
        $startsHere = $this->notStartedOrder([$this->snapshotStep(1, $this->exposure)]);
        $this->notStartedOrder([$this->snapshotStep(1, $this->pretest)]); // starts elsewhere

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->get("/operator/queue?workstation={$this->exposure->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('operator/Queue')
                ->has('workstationQueue', 1)
                ->where('workstationQueue.0.id', $this->atExposure->id)
                ->has('workstationNotStarted', 1)
                ->where('workstationNotStarted.0.id', $startsHere->id)
            );

    }

    public function test_queue_without_a_selection_has_no_workstation_lists(): void
    {
        $this->notStartedOrder([$this->snapshotStep(1, $this->exposure)]);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->get('/operator/queue')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('workstationQueue', 0)
                ->has('workstationNotStarted', 0)
            );
    }

    public function test_queue_check_counts_ready_and_not_started_orders(): void
    {
        $this->notStartedOrder([$this->snapshotStep(1, $this->exposure)]);
        $this->notStartedOrder([$this->snapshotStep(1, $this->pretest)]);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id, 'selected_workstation_id' => $this->exposure->id])
            ->getJson('/operator/queue/check')
            ->assertOk()
            ->assertJson(['active' => 4, 'workstation' => 2]);
    }

    public function test_guest_cannot_poll_the_queue_check(): void
    {
        $this->get('/operator/queue/check')->assertRedirect('/login');
    }

    public function test_selection_made_in_the_queue_carries_over(): void
    {
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->get("/operator/queue?workstation={$this->pretest->id}")
            ->assertOk();

        $this->get('/operator/workstation')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selectedWorkstation.id', $this->pretest->id)
                ->has('workOrders', 1)
                ->where('workOrders.0.id', $this->atPretest->id)
            );
    }

    public function test_all_clears_the_selection_for_both_views(): void
    {
        $this->workstationView("?workstation={$this->exposure->id}")->assertOk();

        $this->get('/operator/workstation?workstation=all')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selectedWorkstation', null)
                ->has('workOrders', 2)
            );

        $this->get('/operator/queue')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('selectedWorkstation', null));
    }

    public function test_workstation_from_another_line_is_ignored(): void
    {
        $foreign = Workstation::factory()->create(['line_id' => Line::factory()->create()->id]);

        $this->workstationView("?workstation={$foreign->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selectedWorkstation', null)
                ->has('workOrders', 2)
            );
    }

    public function test_per_line_tracking_keeps_the_whole_line_view(): void
    {
        $this->setTrackingMode('per_line');

        $this->workstationView("?workstation={$this->exposure->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selectedWorkstation', null)
                ->has('workOrders', 2)
                ->has('machineStates', 2)
            );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get("/operator/workstation?workstation={$this->exposure->id}")
            ->assertRedirect('/login');
    }

    public function test_user_without_an_operator_role_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['selected_line_id' => $this->line->id])
            ->get("/operator/workstation?workstation={$this->exposure->id}")
            ->assertForbidden();
    }
}
