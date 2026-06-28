<?php

namespace Tests\Unit\Services;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use App\Services\Production\OperatorProductionRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorProductionRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private OperatorProductionRateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OperatorProductionRateService::class);
    }

    /**
     * Record a completed step run by $operator on $workstation, on a batch that
     * produced $producedQty over $durationMinutes.
     */
    private function recordStep(
        User $operator,
        Workstation $workstation,
        float $producedQty,
        int $durationMinutes,
        ?string $completedAt = null,
    ): BatchStep {
        $batch = Batch::factory()->create([
            'status' => Batch::STATUS_DONE,
            'produced_qty' => $producedQty,
        ]);

        return BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'status' => BatchStep::STATUS_DONE,
            'workstation_id' => $workstation->id,
            'completed_by_id' => $operator->id,
            'duration_minutes' => $durationMinutes,
            'completed_at' => $completedAt ?? now(),
        ]);
    }

    public function test_computes_units_per_hour_for_a_pair(): void
    {
        $operator = User::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => Line::factory()]);

        // 100 units over 120 minutes (2h) → 50 units/hour.
        $this->recordStep($operator, $workstation, 100, 120);

        $rate = $this->service->rateFor($operator->id, $workstation->id);

        $this->assertNotNull($rate);
        $this->assertSame(50.0, $rate['units_per_hour']);
        $this->assertSame(100.0, $rate['produced_units']);
        $this->assertSame(2.0, $rate['hours']);
        $this->assertSame(1, $rate['steps_count']);
        $this->assertSame($operator->id, $rate['operator_id']);
        $this->assertSame($workstation->id, $rate['workstation_id']);
    }

    public function test_aggregates_multiple_steps_for_the_same_pair(): void
    {
        $operator = User::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => Line::factory()]);

        // 100 units in 60 min + 100 units in 60 min → 200 units over 2h = 100/h.
        $this->recordStep($operator, $workstation, 100, 60);
        $this->recordStep($operator, $workstation, 100, 60);

        $rate = $this->service->rateFor($operator->id, $workstation->id);

        $this->assertSame(100.0, $rate['units_per_hour']);
        $this->assertSame(200.0, $rate['produced_units']);
        $this->assertSame(2, $rate['steps_count']);
    }

    public function test_separates_pairs_by_operator_and_machine(): void
    {
        $fast = User::factory()->create();
        $slow = User::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => Line::factory()]);

        $this->recordStep($fast, $workstation, 100, 60);  // 100/h
        $this->recordStep($slow, $workstation, 50, 60);   // 50/h

        $rates = $this->service->rates();

        $this->assertCount(2, $rates);
        // Sorted fastest first.
        $this->assertSame($fast->id, $rates->first()['operator_id']);
        $this->assertSame(100.0, $rates->first()['units_per_hour']);
    }

    public function test_filters_by_line(): void
    {
        $operator = User::factory()->create();
        $lineA = Line::factory()->create();
        $lineB = Line::factory()->create();
        $wsA = Workstation::factory()->create(['line_id' => $lineA->id]);
        $wsB = Workstation::factory()->create(['line_id' => $lineB->id]);

        $this->recordStep($operator, $wsA, 100, 60);
        $this->recordStep($operator, $wsB, 100, 60);

        $ratesA = $this->service->rates($lineA->id);

        $this->assertCount(1, $ratesA);
        $this->assertSame($wsA->id, $ratesA->first()['workstation_id']);
        $this->assertSame($lineA->id, $ratesA->first()['line_id']);
    }

    public function test_rate_for_returns_null_for_a_machine_the_worker_never_ran(): void
    {
        // Empty-history / no-data state: the pair has no completed steps.
        $operator = User::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => Line::factory()]);

        $this->assertNull($this->service->rateFor($operator->id, $workstation->id));
    }

    public function test_ignores_steps_without_duration_or_produced_quantity(): void
    {
        $operator = User::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => Line::factory()]);

        // No duration → cannot compute a rate.
        $noDuration = Batch::factory()->create(['status' => Batch::STATUS_DONE, 'produced_qty' => 100]);
        BatchStep::factory()->create([
            'batch_id' => $noDuration->id,
            'status' => BatchStep::STATUS_DONE,
            'workstation_id' => $workstation->id,
            'completed_by_id' => $operator->id,
            'duration_minutes' => null,
            'completed_at' => now(),
        ]);

        // Zero produced → no throughput to attribute.
        $this->recordStep($operator, $workstation, 0, 60);

        $this->assertNull($this->service->rateFor($operator->id, $workstation->id));
    }
}
