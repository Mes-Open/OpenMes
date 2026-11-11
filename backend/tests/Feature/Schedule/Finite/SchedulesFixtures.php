<?php

namespace Tests\Feature\Schedule\Finite;

use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Models\Line;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationType;
use App\Services\Schedule\Finite\FiniteScheduler;
use App\Services\Schedule\Finite\SchedulingRequest;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Shared fixtures for finite-scheduling tests: a fixed Monday, one global
 * 06:00–14:00 weekday shift, and helpers to build orders whose routing is
 * written straight into the frozen process snapshot.
 */
trait SchedulesFixtures
{
    protected Carbon $monday;

    protected function setUpSchedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 06:00:00'));
        $this->monday = Carbon::parse('2026-10-12 06:00:00');
        Shift::create(['name' => 'Day', 'code' => 'D', 'start_time' => '06:00', 'end_time' => '14:00', 'days_of_week' => [1, 2, 3, 4, 5], 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function line(array $attrs = []): Line
    {
        return Line::factory()->create(['is_active' => true] + $attrs);
    }

    protected function station(Line $line, ?WorkstationType $type = null, array $attrs = []): Workstation
    {
        return Workstation::factory()->create([
            'line_id' => $line->id,
            'workstation_type_id' => $type?->id,
            'is_active' => true,
        ] + $attrs);
    }

    /**
     * @param  list<array<string, mixed>>  $steps  snapshot step overrides; step_number defaults to 10, 20, …
     */
    protected function order(?Line $line, array $steps, array $attrs = []): WorkOrder
    {
        $snapshotSteps = [];
        foreach ($steps as $i => $step) {
            $snapshotSteps[] = $step + [
                'step_number' => ($i + 1) * 10,
                'name' => 'Step '.($i + 1),
                'estimated_duration_minutes' => null,
                'setup_time_minutes' => null,
                'run_time_per_unit_minutes' => null,
                'required_operators' => 1,
                'workstation_id' => null,
                'workstation_type_id' => null,
            ];
        }

        return WorkOrder::factory()->create($attrs + [
            'line_id' => $line?->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_qty' => 10,
            'priority' => 0,
            'due_date' => null,
            'planned_start_at' => null,
            'planned_end_at' => null,
            'process_snapshot' => ['steps' => $snapshotSteps, 'bom' => []],
        ]);
    }

    protected function request(array $overrides = []): SchedulingRequest
    {
        return new SchedulingRequest(
            start: $overrides['start'] ?? $this->monday->copy(),
            end: $overrides['end'] ?? $this->monday->copy()->addDays(14),
            scope: $overrides['scope'] ?? SchedulingRequest::SCOPE_UNPLANNED,
            respectMaterial: $overrides['respectMaterial'] ?? true,
            workOrderIds: $overrides['workOrderIds'] ?? null,
            respectWorkforce: $overrides['respectWorkforce'] ?? true,
        );
    }

    protected function schedule(array $overrides = []): array
    {
        return app(FiniteScheduler::class)->schedule($this->request($overrides));
    }

    /** @return list<array<string, mixed>> */
    protected function opsFor(array $result, WorkOrder $order): array
    {
        return array_values(array_filter($result['operations'], fn ($op) => $op['work_order_id'] === $order->id));
    }

    protected function at(string $dayAndTime): Carbon
    {
        return Carbon::parse($dayAndTime);
    }

    protected function fakeWorkforce(?int $operators, ?int $qualified = null): void
    {
        $this->app->instance(WorkforceCapacityProvider::class, new class($operators, $qualified) implements WorkforceCapacityProvider
        {
            public function __construct(private ?int $operators, private ?int $qualified) {}

            public function availableOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?int
            {
                return $this->operators;
            }

            public function qualifiedOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?int
            {
                return $this->qualified;
            }
        });
    }
}
