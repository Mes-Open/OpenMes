<?php

namespace Tests\Feature;

use App\Models\Workstation;
use App\Models\MachineEvent;
use App\Models\CycleTimeLog;
use App\Services\EventStore\MachineEventStore;
use App\Services\Analytics\FaultIntelligenceService;
use App\Services\Analytics\OeeCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedIndustrialMesTest extends TestCase
{
    use RefreshDatabase;

    protected MachineEventStore $eventStore;
    protected FaultIntelligenceService $faultService;
    protected OeeCalculationService $oeeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventStore = app(MachineEventStore::class);
        $this->faultService = app(FaultIntelligenceService::class);
        $this->oeeService = app(OeeCalculationService::class);
    }

    public function test_event_store_persists_machine_events(): void
    {
        $line = \App\Models\Line::factory()->create();
        $ws = Workstation::factory()->create(['state' => 'IDLE', 'line_id' => $line->id]);

        $this->eventStore->record($ws, 'STATE_CHANGE', ['state' => 'RUNNING']);

        $this->assertDatabaseHas('machine_events', [
            'workstation_id' => $ws->id,
            'event_type' => 'STATE_CHANGE',
            'state_from' => 'IDLE',
            'state_to' => 'RUNNING',
        ]);
    }

    public function test_fault_intelligence_calculates_mtbf_and_mttr(): void
    {
        $line = \App\Models\Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $line->id]);

        // Simulate 2 faults
        MachineEvent::create([
            'workstation_id' => $ws->id,
            'event_type' => 'FAULT',
            'event_timestamp' => now()->subMinutes(60),
            'payload' => ['fault_code' => 'E01']
        ]);
        MachineEvent::create([
            'workstation_id' => $ws->id,
            'event_type' => 'FAULT',
            'event_timestamp' => now()->subMinutes(30),
            'payload' => ['fault_code' => 'E01']
        ]);

        // Simulate 100s run time
        $batch = \App\Models\Batch::factory()->create();
        CycleTimeLog::create([
            'workstation_id' => $ws->id,
            'batch_id' => $batch->id,
            'cycle_time_secs' => 100,
            'ideal_time_secs' => 90,
            'variability_pct' => 0.1,
            'completed_at' => now()
        ]);

        $mtbf = $this->faultService->calculateMtbf($ws, now()->subDay(), now());
        $this->assertEquals(50, $mtbf); // 100s / 2 faults
    }

    public function test_oee_calculation_with_real_machine_data(): void
    {
        $line = \App\Models\Line::factory()->create();
        $ws = Workstation::factory()->create(['ideal_cycle_time_secs' => 10, 'line_id' => $line->id]);

        // RUNNING state for 60 seconds
        $ws->states()->create([
            'state' => 'RUNNING',
            'started_at' => now()->subSeconds(60),
            'ended_at' => now(),
            'duration_seconds' => 60,
        ]);

        $oeeData = $this->oeeService->calculateOee($ws, now()->subMinutes(10), now());

        $this->assertArrayHasKey('oee', $oeeData);
        $this->assertArrayHasKey('availability', $oeeData);
        $this->assertArrayHasKey('performance', $oeeData);
    }
}
