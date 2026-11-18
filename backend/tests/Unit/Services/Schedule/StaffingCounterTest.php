<?php

namespace Tests\Unit\Services\Schedule;

use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Extension\Contracts\WorkforceRosterAware;
use App\Services\Schedule\Finite\StaffingCounter;
use App\Services\Schedule\Finite\Timeline;
use Carbon\CarbonInterface;
use Tests\TestCase;

/**
 * The count behind the scheduler's staffing grid and the planner's badges:
 * people available for a line against people its booked work needs, someone
 * on two lines counted once.
 */
class StaffingCounterTest extends TestCase
{
    /** @param array<int, list<string>> $roster line => person keys present */
    private function roster(array $roster): WorkforceCapacityProvider
    {
        return new class($roster) implements WorkforceCapacityProvider, WorkforceRosterAware
        {
            public function __construct(private array $roster) {}

            public function availableOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?int
            {
                return isset($this->roster[$lineId]) ? count($this->roster[$lineId]) : null;
            }

            public function qualifiedOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?int
            {
                return null;
            }

            public function assignedOperatorKeys(): array
            {
                return $this->roster;
            }

            public function presentOperatorKeys(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?array
            {
                return $this->roster[$lineId] ?? null;
            }

            public function qualifiedOperatorKeys(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?array
            {
                return null;
            }
        };
    }

    private function counter(WorkforceCapacityProvider $provider, Timeline $timeline): StaffingCounter
    {
        return new StaffingCounter($provider, $timeline, StaffingCounter::poolFor($provider, [1, 2]));
    }

    public function test_a_line_of_its_own_counts_its_people(): void
    {
        $timeline = new Timeline;
        $timeline->bookLabor(1, 0, 480, 2);

        $count = $this->counter($this->roster([1 => ['w:1', 'w:2', 'w:3'], 2 => ['w:4']]), $timeline)->count(1, 0, 480);

        $this->assertSame(['tracked' => true, 'present' => 3, 'available' => 3, 'needed' => 2, 'held_elsewhere' => []], $count);
    }

    public function test_a_line_nobody_is_assigned_to_is_not_tracked(): void
    {
        $timeline = new Timeline;
        $timeline->bookLabor(2, 0, 60, 1);

        $count = $this->counter($this->roster([1 => ['w:1']]), $timeline)->count(2, 0, 480);

        $this->assertFalse($count['tracked']);
        $this->assertNull($count['available']);
        $this->assertSame(1, $count['needed']);
    }

    public function test_the_shared_person_is_held_by_the_other_lines_work_at_the_same_time(): void
    {
        $timeline = new Timeline;
        $timeline->bookLabor(1, 0, 60, 1);
        $timeline->bookLabor(2, 0, 60, 1);

        $count = $this->counter($this->roster([1 => ['w:9'], 2 => ['w:9']]), $timeline)->count(1, 0, 480);

        $this->assertSame(1, $count['present']);
        $this->assertSame(0, $count['available']);
        $this->assertSame(1, $count['needed']);
        $this->assertSame([2 => ['w:9']], $count['held_elsewhere']);
    }

    public function test_the_shared_person_working_one_line_after_the_other_is_enough_for_both(): void
    {
        $timeline = new Timeline;
        $timeline->bookLabor(1, 0, 60, 1);
        $timeline->bookLabor(2, 60, 120, 1);
        $counter = $this->counter($this->roster([1 => ['w:9'], 2 => ['w:9']]), $timeline);

        foreach ([1, 2] as $line) {
            $count = $counter->count($line, 0, 480);
            $this->assertSame([1, 1], [$count['available'], $count['needed']]);
        }
    }

    public function test_own_people_cover_the_line_before_shared_ones_are_missed(): void
    {
        // Line 1: own w:1 plus shared w:9; line 2 needs w:9 at the same time.
        $timeline = new Timeline;
        $timeline->bookLabor(1, 0, 60, 2);
        $timeline->bookLabor(2, 0, 60, 1);

        $count = $this->counter($this->roster([1 => ['w:1', 'w:9'], 2 => ['w:9']]), $timeline)->count(1, 0, 480);

        $this->assertSame(1, $count['available']);
        $this->assertSame(2, $count['needed']);
    }

    public function test_with_no_work_on_the_line_available_is_who_is_free_for_it(): void
    {
        $timeline = new Timeline;
        $timeline->bookLabor(2, 0, 60, 1);

        $count = $this->counter($this->roster([1 => ['w:1', 'w:9'], 2 => ['w:9']]), $timeline)->count(1, 0, 480);

        $this->assertSame(0, $count['needed']);
        $this->assertSame(1, $count['available']);
    }

    public function test_a_count_only_provider_keeps_lines_separate(): void
    {
        $provider = new class implements WorkforceCapacityProvider
        {
            public function availableOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?int
            {
                return 1;
            }

            public function qualifiedOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?int
            {
                return 1;
            }
        };
        $timeline = new Timeline;
        $timeline->bookLabor(1, 0, 60, 1);
        $timeline->bookLabor(2, 0, 60, 1);

        $this->assertNull(StaffingCounter::poolFor($provider, [1, 2]));
        $count = (new StaffingCounter($provider, $timeline, null))->count(1, 0, 480);
        $this->assertSame([1, 1], [$count['available'], $count['needed']]);
    }
}
