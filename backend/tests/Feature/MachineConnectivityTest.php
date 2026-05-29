<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineEvent;
use App\Models\MachineTag;
use App\Models\ProductionDowntime;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Machine\MachineMonitorService;
use App\Services\Machine\MachineSignalIngestor;
use App\Services\Machine\Modbus\ModbusReader;
use App\Services\Machine\WorkstationStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineConnectivityTest extends TestCase
{
    use RefreshDatabase;

    private function workstation(): Workstation
    {
        return Workstation::factory()->create(['line_id' => Line::factory()]);
    }

    private function tag(Workstation $ws, string $signal, array $attrs = []): MachineTag
    {
        $conn = MachineConnection::create([
            'name' => 'Test', 'protocol' => 'modbus', 'is_active' => true, 'status' => 'disconnected',
        ]);

        return MachineTag::create(array_merge([
            'machine_connection_id' => $conn->id,
            'workstation_id' => $ws->id,
            'name' => $signal,
            'address' => '0',
            'signal_type' => $signal,
            'data_type' => 'int16',
            'register_type' => 'holding',
        ], $attrs));
    }

    // ── Tag transform ────────────────────────────────────────────

    public function test_tag_value_map_transform(): void
    {
        $tag = $this->tag($this->workstation(), 'state', [
            'transform' => ['value_map' => ['1' => 'RUNNING', '2' => 'IDLE', '3' => 'FAULT']],
        ]);

        $this->assertEquals('RUNNING', $tag->applyTransform(1));
        $this->assertEquals('FAULT', $tag->applyTransform(3));
    }

    public function test_tag_scale_offset_transform(): void
    {
        $tag = $this->tag($this->workstation(), 'telemetry', ['transform' => ['scale' => 0.1, 'offset' => 5]]);
        $this->assertEquals(27.0, $tag->applyTransform(220)); // 220*0.1 + 5
    }

    // ── State machine ────────────────────────────────────────────

    public function test_transition_opens_and_closes_states(): void
    {
        $ws = $this->workstation();
        $sm = app(WorkstationStateMachine::class);

        $sm->transition($ws, WorkstationState::RUNNING);
        $this->assertEquals('RUNNING', $sm->current($ws)->state);

        $sm->transition($ws, WorkstationState::IDLE);
        $this->assertEquals('IDLE', $sm->current($ws)->state);
        // Previous RUNNING slice is closed
        $this->assertEquals(1, WorkstationState::where('workstation_id', $ws->id)->whereNotNull('ended_at')->count());
    }

    public function test_fault_opens_auto_downtime_and_recovery_closes_it(): void
    {
        $ws = $this->workstation();
        $sm = app(WorkstationStateMachine::class);

        $sm->transition($ws, WorkstationState::RUNNING);
        $sm->transition($ws, WorkstationState::FAULT);
        $this->assertEquals(1, ProductionDowntime::where('workstation_id', $ws->id)->whereNull('ended_at')->count());

        $sm->transition($ws, WorkstationState::RUNNING);
        $this->assertEquals(0, ProductionDowntime::where('workstation_id', $ws->id)->whereNull('ended_at')->count());
        $this->assertEquals(1, ProductionDowntime::where('workstation_id', $ws->id)->whereNotNull('ended_at')->count());
    }

    // ── Ingestor ─────────────────────────────────────────────────

    public function test_ingest_state_signal_drives_state_and_event(): void
    {
        $ws = $this->workstation();
        $tag = $this->tag($ws, 'state', ['transform' => ['value_map' => ['1' => 'RUNNING', '3' => 'FAULT']]]);

        app(MachineSignalIngestor::class)->ingest($tag, 1);

        $this->assertEquals('RUNNING', app(WorkstationStateMachine::class)->current($ws)->state);
        $this->assertEquals(1, MachineEvent::where('event_type', 'state_change')->count());
    }

    public function test_ingest_counter_emits_delta_only(): void
    {
        $ws = $this->workstation();
        $tag = $this->tag($ws, 'good_count');
        $ingestor = app(MachineSignalIngestor::class);

        $ingestor->ingest($tag, 10); // first reading → baseline, delta 0
        $ingestor->ingest($tag, 13); // delta 3

        $events = MachineEvent::where('event_type', 'counter')->get();
        $this->assertCount(1, $events);
        $this->assertEquals(3, $events->first()->payload['delta']);
    }

    // ── Monitor ──────────────────────────────────────────────────

    public function test_live_status_reports_state_and_counts(): void
    {
        $ws = $this->workstation();
        $stateTag = $this->tag($ws, 'state', ['transform' => ['value_map' => ['1' => 'RUNNING']]]);
        $goodTag = $this->tag($ws, 'good_count');
        $ingestor = app(MachineSignalIngestor::class);

        $ingestor->ingest($stateTag, 1);
        $ingestor->ingest($goodTag, 0);
        $ingestor->ingest($goodTag, 5);

        $status = app(MachineMonitorService::class)->liveStatus($ws);
        $this->assertEquals('RUNNING', $status['state']);
        $this->assertEquals(5, $status['good']);
    }

    // ── Modbus address normalization ─────────────────────────────

    public function test_modbus_address_normalization(): void
    {
        $modbus = new \App\Models\ModbusConnection(['host' => 'x', 'port' => 502, 'unit_id' => 1, 'byte_order' => 'big', 'word_order' => 'big', 'timeout_seconds' => 1]);
        $reader = new ModbusReader($modbus);
        $ref = new \ReflectionMethod($reader, 'normalizeAddress');
        $ref->setAccessible(true);

        $this->assertEquals(5, $ref->invoke($reader, '40006')); // Modicon holding → 0-based
        $this->assertEquals(1, $ref->invoke($reader, '30002')); // Modicon input
        $this->assertEquals(7, $ref->invoke($reader, '7'));     // raw offset
    }
}
