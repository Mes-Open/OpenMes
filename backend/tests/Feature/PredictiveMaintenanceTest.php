<?php

namespace Tests\Feature;

use App\Models\Workstation;
use App\Models\Tool;
use App\Models\MaintenanceEvent;
use App\Models\Line;
use App\Models\MachineEvent;
use App\Models\CycleTimeLog;
use App\Models\Batch;
use App\Services\Industrial\PredictiveMaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictiveMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected PredictiveMaintenanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PredictiveMaintenanceService::class);
    }

    public function test_workstation_health_analysis_updates_failure_probability(): void
    {
        $line = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $line->id]);

        // Simulate MTBF: 1 fault in last 30 days, 1000s total run time
        $event = new MachineEvent();
        $event->workstation_id = $ws->id;
        $event->event_type = 'FAULT';
        $event->event_timestamp = now()->subHours(5);
        $event->payload = ['fault_code' => 'E01'];
        $event->save();

        $ws->states()->create([
            'state' => 'FAULT',
            'started_at' => now()->subHours(5),
            'ended_at' => now()->subHours(4),
        ]);

        $batch = Batch::factory()->create();
        CycleTimeLog::create([
            'workstation_id' => $ws->id,
            'batch_id' => $batch->id,
            'cycle_time_secs' => 1000,
            'ideal_time_secs' => 900,
            'variability_pct' => 0.1,
            'completed_at' => now()->subMinutes(10)
        ]);

        $this->service->analyzeWorkstationHealth($ws);

        $ws->refresh();
        $this->assertGreaterThan(0, $ws->failure_probability);
    }

    public function test_tool_health_analysis_triggers_maintenance(): void
    {
        $tool = Tool::factory()->create([
            'max_cycles' => 100,
            'current_cycles' => 95,
            'maintenance_threshold' => 90,
        ]);

        $this->service->analyzeToolHealth($tool);

        $this->assertDatabaseHas('maintenance_events', [
            'tool_id' => $tool->id,
            'status' => MaintenanceEvent::STATUS_PENDING,
        ]);
    }

    public function test_artisan_command_runs_analysis(): void
    {
        $line = Line::factory()->create();
        Workstation::factory()->count(2)->create(['line_id' => $line->id, 'is_active' => true]);
        Tool::factory()->count(2)->create(['status' => 'available']);

        $this->artisan('industrial:monitor-health')
            ->assertExitCode(0)
            ->expectsOutputToContain('Starting health analysis...')
            ->expectsOutputToContain('Health analysis complete.');
    }
}
