<?php

namespace Tests\Feature\Machine;

use App\Models\Batch;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineEvent;
use App\Models\MachineTag;
use App\Models\ProductType;
use App\Models\QualityCheck;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The MES half of the machine simulation: `demo:simulate-workflow` opening and
 * closing batches against the counts the poller recorded, signing off quality
 * checks, and escalating a failed one.
 *
 * The counts themselves are never invented, and real production data is never
 * touched — both are asserted here, because both are the difference between a
 * simulator and a data-corruption incident.
 */
class SimulateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Workstation $workstation;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        $line = Line::factory()->create();
        $this->workstation = Workstation::factory()->create([
            'line_id' => $line->id,
            'code' => 'TEST-1',
            'ideal_rate_per_hour' => 600,   // 10 pcs/min
        ]);

        ProductType::factory()->create();
        User::factory()->create();

        $this->simulatorTag($this->workstation);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Wire a workstation to a simulator connection, the way the seeder does. */
    private function simulatorTag(Workstation $workstation, string $name = 'SIM-TEST-1'): MachineTag
    {
        $connection = MachineConnection::create([
            'name' => $name,
            'protocol' => 'modbus',
            'is_active' => true,
        ]);

        return MachineTag::create([
            'machine_connection_id' => $connection->id,
            'workstation_id' => $workstation->id,
            'name' => 'Good count',
            'address' => '1',
            'signal_type' => MachineTag::SIGNAL_GOOD_COUNT,
            'data_type' => 'uint16',
            'register_type' => 'holding',
            'is_active' => true,
        ]);
    }

    private function counted(int $delta, string $kind = 'good', ?Carbon $at = null): MachineEvent
    {
        return MachineEvent::create([
            'workstation_id' => $this->workstation->id,
            'event_type' => MachineEvent::TYPE_COUNTER,
            'event_timestamp' => $at ?? now(),
            'payload' => ['delta' => $delta, 'kind' => $kind],
        ]);
    }

    /**
     * Records belonging to this test's station.
     *
     * Scoped rather than global: a developer's database can already hold the
     * seeded `SIM-*` connections for the docker simulators, and the command is
     * meant to find every one of them — so `QualityCheck::first()` would be
     * whichever station happened to be checked first.
     *
     * @return \Illuminate\Database\Eloquent\Builder<QualityCheck>
     */
    private function checks()
    {
        return QualityCheck::whereIn(
            'batch_id',
            Batch::where('workstation_id', $this->workstation->id)->select('id')
        );
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Issue> */
    private function issues()
    {
        return Issue::whereIn(
            'work_order_id',
            Batch::where('workstation_id', $this->workstation->id)->select('work_order_id')
        );
    }

    /** @param  array<string, mixed>  $options */
    private function tick(array $options = []): void
    {
        $this->artisan('demo:simulate-workflow', array_merge([
            '--once' => true,
            // Off unless a test asks for it, so batch assertions are not
            // entangled with quality checks firing on the same tick.
            '--qc-minutes' => 10_000,
        ], $options))->assertSuccessful();
    }

    public function test_a_station_with_no_batch_gets_one_on_its_own_work_order(): void
    {
        $this->tick();

        $batch = Batch::where('workstation_id', $this->workstation->id)->firstOrFail();

        $this->assertSame(Batch::STATUS_IN_PROGRESS, $batch->status);
        // 25 nominal minutes at 10 pcs/min.
        $this->assertEquals(250, (float) $batch->target_qty);

        $order = $batch->workOrder;
        $this->assertStringStartsWith('SIM-', $order->order_no);
        // Machine-counted, or the ingest path would refuse to move produced_qty
        // on it and the order would sit at zero all shift.
        $this->assertTrue($order->isMachineCounted());
    }

    public function test_it_leaves_real_work_orders_alone(): void
    {
        $existing = WorkOrder::create([
            'order_no' => 'WO-REAL-1',
            'line_id' => $this->workstation->line_id,
            'planned_qty' => 25,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'counting_source' => WorkOrder::COUNTING_OPERATOR,
        ]);

        $this->tick();

        $existing->refresh();
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $existing->status);
        $this->assertSame(0, Batch::where('work_order_id', $existing->id)->count());
    }

    public function test_a_batch_it_did_not_open_is_left_alone(): void
    {
        $order = WorkOrder::create([
            'order_no' => 'WO-REAL-2',
            'line_id' => $this->workstation->line_id,
            'planned_qty' => 1000,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'counting_source' => WorkOrder::COUNTING_OPERATOR,
        ]);
        $foreign = Batch::create([
            'work_order_id' => $order->id,
            'workstation_id' => $this->workstation->id,
            'batch_number' => 1,
            'target_qty' => 1000,
            'produced_qty' => 40,
            'status' => Batch::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
        ]);

        $this->counted(500);
        $this->tick();

        // Untouched — writing this simulation's counts over an operator's batch
        // would be indistinguishable from a data-corruption bug.
        $foreign->refresh();
        $this->assertEquals(40, (float) $foreign->produced_qty);
        $this->assertSame(Batch::STATUS_IN_PROGRESS, $foreign->status);

        // And no competing batch opened next to it, which would double-count
        // everything the station makes.
        $this->assertSame(1, Batch::where('workstation_id', $this->workstation->id)->count());
    }

    public function test_a_station_behind_no_simulator_is_never_touched(): void
    {
        $other = Workstation::factory()->create([
            'line_id' => $this->workstation->line_id,
            'code' => 'REAL-1',
            'ideal_rate_per_hour' => 600,
        ]);

        $this->tick();

        $this->assertSame(0, Batch::where('workstation_id', $other->id)->count());
    }

    public function test_the_batch_only_ever_claims_what_the_machine_reported(): void
    {
        $this->tick();                       // opens the batch

        $this->counted(120);
        $this->counted(4, 'reject');
        $this->tick();

        $batch = Batch::where('workstation_id', $this->workstation->id)->firstOrFail();
        $this->assertEquals(120, (float) $batch->produced_qty);
        $this->assertEquals(4, (float) $batch->scrap_qty);
    }

    public function test_counts_are_recomputed_not_accumulated(): void
    {
        $this->tick();
        $this->counted(50);

        // Two ticks over the same pulse must not count it twice — the command
        // is restarted by its container whenever the image changes, and a
        // rerun that doubled the batch would make the numbers meaningless.
        $this->tick();
        $this->tick();

        $this->assertEquals(50, (float) Batch::where('workstation_id', $this->workstation->id)->value('produced_qty'));
    }

    public function test_counts_from_before_the_batch_opened_do_not_land_on_it(): void
    {
        $this->counted(999, 'good', now()->subHour());

        $this->tick();
        $this->tick();

        $this->assertEquals(0, (float) Batch::where('workstation_id', $this->workstation->id)->value('produced_qty'));
    }

    public function test_a_batch_that_reaches_target_closes_and_the_next_one_opens(): void
    {
        $this->tick();

        $first = Batch::where('workstation_id', $this->workstation->id)->firstOrFail();
        $this->counted(250);
        $this->tick();

        $first->refresh();
        $this->assertSame(Batch::STATUS_DONE, $first->status);
        $this->assertNotNull($first->completed_at);
        $this->assertEquals(250, (float) $first->produced_qty);

        $open = Batch::where('workstation_id', $this->workstation->id)
            ->where('status', Batch::STATUS_IN_PROGRESS)
            ->get();

        // Exactly one, and not the one just closed: an empty batch strip reads
        // as a stopped station, and two open batches would double-count.
        $this->assertCount(1, $open);
        $this->assertNotSame($first->id, $open->first()->id);
    }

    public function test_a_finished_order_is_closed_and_replaced(): void
    {
        // Four batches to an order; run each one to target.
        for ($i = 0; $i < 5; $i++) {
            $this->tick();
            $this->counted(250);
            $this->tick();
        }

        $orders = WorkOrder::where('order_no', 'like', 'SIM-TEST-1-%')->orderBy('id')->get();

        $this->assertGreaterThan(1, $orders->count());
        $this->assertSame(WorkOrder::STATUS_DONE, $orders->first()->status);
        $this->assertNotSame($orders->first()->product_type_id, $orders->last()->product_type_id ?? null);
    }

    public function test_a_quality_check_is_signed_off_on_a_running_batch(): void
    {
        $this->tick();
        $this->counted(60);

        // Never fails, so this test is only about the check being recorded.
        $this->tick(['--qc-minutes' => 0, '--fail-in' => 1_000_000]);

        $check = $this->checks()->firstOrFail();

        $this->assertTrue($check->all_passed);
        $this->assertEquals(60, (float) $check->production_quantity);
        $this->assertNotNull($check->checked_by);
        $this->assertCount(3, $check->samples);
        $this->assertSame(0, $this->issues()->count());
    }

    public function test_a_failed_check_raises_an_issue_against_the_order(): void
    {
        IssueType::factory()->create(['is_active' => true]);

        $this->tick();
        $this->counted(60);
        $this->tick(['--qc-minutes' => 0, '--fail-in' => 1]);

        $check = $this->checks()->firstOrFail();
        $this->assertFalse($check->all_passed);
        // One parameter out of tolerance, not the whole gauge.
        $this->assertCount(1, $check->samples->where('is_passed', false));

        $issue = $this->issues()->firstOrFail();
        $this->assertSame(Issue::STATUS_OPEN, $issue->status);
        $this->assertSame($check->batch->work_order_id, $issue->work_order_id);
    }

    public function test_a_check_is_not_repeated_before_its_interval_is_up(): void
    {
        $this->tick();
        $this->counted(60);

        $this->tick(['--qc-minutes' => 0, '--fail-in' => 1_000_000]);
        $this->tick(['--qc-minutes' => 8, '--fail-in' => 1_000_000]);

        $this->assertSame(1, $this->checks()->count());
    }

    public function test_it_refuses_to_run_with_no_simulated_stations(): void
    {
        MachineConnection::query()->delete();

        $this->artisan('demo:simulate-workflow', ['--once' => true])->assertFailed();
    }
}
