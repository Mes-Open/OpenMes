<?php

namespace Tests\Feature\Machine;

use App\Events\Machine\ShiftMonitorChanged;
use App\Events\Machine\WorkstationStateChanged;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Machine\MachineSignalIngestor;
use App\Services\Machine\WorkstationStateMachine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Every ingested signal marks the station as heard from by bumping its open
 * state row's `updated_at` — throttled, and without model events, so a healthy
 * poller re-reading the same state neither writes every cycle nor nudges the
 * shift monitor. The plant overview reads it to tell a silent collector apart.
 */
class MachineSignalTouchTest extends TestCase
{
    use RefreshDatabase;

    private Workstation $station;

    private MachineConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        $this->station = Workstation::factory()->create(['line_id' => Line::factory()]);
        $this->connection = MachineConnection::create([
            'name' => 'Press', 'protocol' => 'modbus', 'is_active' => true, 'status' => 'disconnected',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tag(string $signal, array $attrs = []): MachineTag
    {
        return MachineTag::create(array_merge([
            'machine_connection_id' => $this->connection->id,
            'workstation_id' => $this->station->id,
            'name' => $signal,
            'address' => '0',
            'signal_type' => $signal,
            'data_type' => 'int16',
            'register_type' => 'holding',
        ], $attrs));
    }

    private function stateTag(): MachineTag
    {
        return $this->tag(MachineTag::SIGNAL_STATE, ['transform' => ['value_map' => ['1' => 'RUNNING', '3' => 'FAULT']]]);
    }

    private function openRow(): WorkstationState
    {
        return WorkstationState::where('workstation_id', $this->station->id)->whereNull('ended_at')->sole();
    }

    public function test_repeated_state_signal_bumps_updated_at_without_a_new_row_or_event(): void
    {
        $tag = $this->stateTag();
        $ingestor = app(MachineSignalIngestor::class);
        $ingestor->ingest($tag, 1, now());

        Event::fake([WorkstationStateChanged::class, ShiftMonitorChanged::class]);
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:02:00'));
        $ingestor->ingest($tag, 1, now());

        $this->assertSame(1, WorkstationState::where('workstation_id', $this->station->id)->count());
        $this->assertEquals(Carbon::parse('2026-05-26 10:02:00'), $this->openRow()->updated_at);
        Event::assertNotDispatched(WorkstationStateChanged::class);
        Event::assertNotDispatched(ShiftMonitorChanged::class);
    }

    public function test_signal_within_the_throttle_window_does_not_write(): void
    {
        $tag = $this->stateTag();
        $ingestor = app(MachineSignalIngestor::class);
        $ingestor->ingest($tag, 1, now());

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:45'));
        $ingestor->ingest($tag, 1, now());

        $this->assertEquals(Carbon::parse('2026-05-26 10:00:00'), $this->openRow()->updated_at);
    }

    public function test_telemetry_and_counter_signals_also_count_as_heard_from(): void
    {
        $ingestor = app(MachineSignalIngestor::class);
        $ingestor->ingest($this->stateTag(), 1, now());

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:05:00'));
        $ingestor->ingest($this->tag(MachineTag::SIGNAL_GOOD_COUNT, ['name' => 'good']), 10, now());
        $this->assertEquals(Carbon::parse('2026-05-26 10:05:00'), $this->openRow()->updated_at);

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:07:00'));
        $ingestor->ingest($this->tag(MachineTag::SIGNAL_TELEMETRY, ['name' => 'temp']), 42, now());
        $this->assertEquals(Carbon::parse('2026-05-26 10:07:00'), $this->openRow()->updated_at);
    }

    public function test_a_burst_of_signals_issues_one_update_a_minute(): void
    {
        $tag = $this->tag(MachineTag::SIGNAL_TELEMETRY, ['name' => 'temp']);
        $ingestor = app(MachineSignalIngestor::class);
        $ingestor->ingest($this->stateTag(), 1, now());
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:02:00'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        foreach (range(1, 5) as $i) {
            $ingestor->ingest($tag, 20 + $i, now());
        }
        $touches = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $q) => str_starts_with($q, 'update "workstation_states" set "updated_at"'));
        DB::disableQueryLog();

        $this->assertCount(1, $touches);
    }

    public function test_an_operator_re_setting_the_same_state_is_not_a_machine_signal(): void
    {
        $machine = app(WorkstationStateMachine::class);
        $machine->transition($this->station, WorkstationState::RUNNING);

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:05:00'));
        $machine->transition($this->station, WorkstationState::RUNNING, [], null, 'manual');

        $this->assertEquals(Carbon::parse('2026-05-26 10:00:00'), $this->openRow()->updated_at);
    }

    public function test_signal_never_touches_closed_rows(): void
    {
        $tag = $this->stateTag();
        $ingestor = app(MachineSignalIngestor::class);
        $ingestor->ingest($tag, 1, now());

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:03:00'));
        $ingestor->ingest($tag, 3, now()); // RUNNING → FAULT closes the first row

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:06:00'));
        $ingestor->ingest($tag, 3, now());

        $closed = WorkstationState::where('workstation_id', $this->station->id)->whereNotNull('ended_at')->sole();
        $this->assertEquals(Carbon::parse('2026-05-26 10:03:00'), $closed->updated_at);
        $this->assertSame(WorkstationState::FAULT, $this->openRow()->state);
        $this->assertEquals(Carbon::parse('2026-05-26 10:06:00'), $this->openRow()->updated_at);
    }
}
