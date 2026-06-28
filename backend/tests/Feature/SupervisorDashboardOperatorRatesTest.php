<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupervisorDashboardOperatorRatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_dashboard_exposes_operator_rates_prop(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $operator = User::factory()->create();
        $line = Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $batch = Batch::factory()->create(['status' => Batch::STATUS_DONE, 'produced_qty' => 100]);
        BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'status' => BatchStep::STATUS_DONE,
            'workstation_id' => $workstation->id,
            'completed_by_id' => $operator->id,
            'duration_minutes' => 60, // 100 units / 1h = 100/h
            'completed_at' => now(),
        ]);

        // Select the workstation's line explicitly - operator rates are scoped to
        // the machine's line, and the dashboard otherwise auto-picks the first.
        $this->actingAs($supervisor)
            ->get(route('supervisor.dashboard', ['line_id' => $line->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('supervisor/Dashboard')
                ->has('operatorRates', 1)
                ->where('operatorRates.0.operator_id', $operator->id)
                ->where('operatorRates.0.workstation_id', $workstation->id)
            );
    }

    public function test_dashboard_operator_rates_empty_without_production(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->get(route('supervisor.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('supervisor/Dashboard')
                ->has('operatorRates', 0)
            );
    }
}
