<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorRatesTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
    }

    private function recordStep(User $operator, Workstation $workstation, float $producedQty, int $durationMinutes): void
    {
        $batch = Batch::factory()->create(['status' => Batch::STATUS_DONE, 'produced_qty' => $producedQty]);
        BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'status' => BatchStep::STATUS_DONE,
            'workstation_id' => $workstation->id,
            'completed_by_id' => $operator->id,
            'duration_minutes' => $durationMinutes,
            'completed_at' => now(),
        ]);
    }

    public function test_lists_operator_rates(): void
    {
        $operator = User::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => Line::factory()]);
        $this->recordStep($operator, $workstation, 100, 120); // 50/h

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson('/api/v1/analytics/operator-rates');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['rates' => [['operator_id', 'workstation_id', 'units_per_hour', 'steps_count']], 'count']]);

        $this->assertSame(1, $response->json('data.count'));
        $this->assertEqualsWithDelta(50, $response->json('data.rates.0.units_per_hour'), 0.01);
        $this->assertSame($operator->id, $response->json('data.rates.0.operator_id'));
    }

    public function test_single_pair_reports_no_data_state(): void
    {
        // A machine the worker has never run → has_history false, rate null.
        $operator = User::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => Line::factory()]);

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson("/api/v1/analytics/operator-rates?operator_id={$operator->id}&workstation_id={$workstation->id}");

        $response->assertOk()
            ->assertJsonPath('data.has_history', false)
            ->assertJsonPath('data.rate', null);
    }

    public function test_single_pair_returns_rate_when_history_exists(): void
    {
        $operator = User::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => Line::factory()]);
        $this->recordStep($operator, $workstation, 100, 60); // 100/h

        $response = $this->actingAs($this->supervisor, 'sanctum')
            ->getJson("/api/v1/analytics/operator-rates?operator_id={$operator->id}&workstation_id={$workstation->id}");

        $response->assertOk()
            ->assertJsonPath('data.has_history', true);
        $this->assertEqualsWithDelta(100, $response->json('data.rate.units_per_hour'), 0.01);
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/analytics/operator-rates')->assertUnauthorized();
    }

    public function test_operator_role_is_forbidden(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/analytics/operator-rates')
            ->assertForbidden();
    }
}
