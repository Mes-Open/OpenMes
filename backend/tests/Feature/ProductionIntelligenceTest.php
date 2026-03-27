<?php

namespace Tests\Feature;

use App\Models\Workstation;
use App\Models\User;
use App\Models\ProductionCycle;
use App\Models\DowntimeEvent;
use App\Models\AnomalyReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ProductionIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $workstation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $line = \App\Models\Line::factory()->create();
        $this->workstation = Workstation::factory()->create(['line_id' => $line->id]);
    }

    public function test_oee_calculation_returns_valid_data()
    {
        // 1. Setup production data
        $start = now()->subHours(8);
        $end = now();

        // Add some production cycles
        ProductionCycle::create([
            'workstation_id' => $this->workstation->id,
            'started_at' => $start->copy()->addMinutes(10),
            'ended_at' => $start->copy()->addMinutes(11),
            'cycle_time_seconds' => 60,
            'ideal_cycle_time_seconds' => 50,
        ]);

        // Add a downtime event
        $reason = AnomalyReason::create(['name' => 'Breakdown', 'code' => 'B01', 'category' => 'Unplanned']);
        DowntimeEvent::create([
            'workstation_id' => $this->workstation->id,
            'anomaly_reason_id' => $reason->id,
            'started_at' => $start->copy()->addHours(1),
            'ended_at' => $start->copy()->addHours(1)->addMinutes(30),
            'downtime_category' => 'Unplanned',
            'duration_minutes' => 30,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/pi/kpi/oee/{$this->workstation->id}?start_date={$start->toDateTimeString()}&end_date={$end->toDateTimeString()}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'oee',
                    'availability',
                    'performance',
                    'quality',
                    'time_model'
                ]
            ]);
    }

    public function test_pareto_analysis_endpoint()
    {
        $start = now()->subDay();
        $end = now();

        $reason = AnomalyReason::create(['name' => 'Motor Failure', 'code' => 'M01', 'category' => 'Unplanned']);
        DowntimeEvent::create([
            'workstation_id' => $this->workstation->id,
            'anomaly_reason_id' => $reason->id,
            'started_at' => $start->copy()->addMinutes(10),
            'ended_at' => $start->copy()->addMinutes(40),
            'downtime_category' => 'Unplanned',
            'duration_minutes' => 30,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/pi/losses/pareto/{$this->workstation->id}?start_date={$start->toDateTimeString()}&end_date={$end->toDateTimeString()}");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.cause', 'Motor Failure');
    }
}
