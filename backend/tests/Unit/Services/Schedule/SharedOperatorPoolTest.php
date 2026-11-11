<?php

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\Finite\SharedOperatorPool;
use App\Services\Schedule\Finite\Timeline;
use PHPUnit\Framework\TestCase;

/**
 * People shared between lines, counted once: line groups, and how many more
 * operators a line can take on at one moment given what is booked in its group.
 */
class SharedOperatorPoolTest extends TestCase
{
    public function test_lines_sharing_anyone_form_one_group(): void
    {
        $pool = new SharedOperatorPool([
            1 => ['a', 'b'],
            2 => ['b'],
            3 => ['c'],
            4 => ['c', 'd'],
            5 => ['e'],
        ]);

        $this->assertSame([1, 2], $pool->groupOf(1));
        $this->assertSame([1, 2], $pool->groupOf(2));
        $this->assertSame([3, 4], $pool->groupOf(4));
        $this->assertSame([5], $pool->groupOf(5));
        $this->assertFalse($pool->isShared(5));
        $this->assertSame([9], $pool->groupOf(9), 'an unknown line is its own group');
        $this->assertSame(1, $pool->privateCount(1, ['a', 'b']));
        $this->assertSame(0, $pool->privateCount(2, ['b']));
    }

    public function test_a_person_shared_by_three_lines_links_them_all(): void
    {
        $pool = new SharedOperatorPool([1 => ['s'], 2 => ['s'], 3 => ['s', 'x']]);
        $present = [1 => ['s'], 2 => ['s'], 3 => ['s', 'x']];

        $this->assertSame([1, 2, 3], $pool->groupOf(2));
        $this->assertSame(['spare' => 0, 'busy' => [1 => 1]], $pool->spare(2, $present, [1 => 1], 1));
        // Line 3 still has its own person.
        $this->assertSame(1, $pool->spare(3, $present, [1 => 1], 1)['spare']);
    }

    public function test_booked_work_is_rerouted_to_free_a_shared_person(): void
    {
        // Line 1 has one booking; it can be staffed by 's' or by its own 'o'.
        $pool = new SharedOperatorPool([1 => ['s', 'o'], 2 => ['s']]);
        $present = [1 => ['s', 'o'], 2 => ['s']];

        $this->assertSame(['spare' => 1, 'busy' => []], $pool->spare(2, $present, [1 => 1], 1));
        // With both of line 1's people booked, nobody is left for line 2.
        $this->assertSame(['spare' => 0, 'busy' => [1 => 1]], $pool->spare(2, $present, [1 => 2], 1));
    }

    public function test_an_over_committed_line_does_not_take_another_lines_own_people(): void
    {
        // Line 1 is booked for three but only two are there; line 2 keeps 'p'.
        $pool = new SharedOperatorPool([1 => ['s', 't'], 2 => ['s', 'p']]);
        $present = [1 => ['s', 't'], 2 => ['s', 'p']];

        $this->assertSame(1, $pool->spare(2, $present, [1 => 3], 2)['spare']);
    }

    public function test_an_absent_person_is_eligible_nowhere(): void
    {
        $pool = new SharedOperatorPool([1 => ['s'], 2 => ['s', 'p']]);

        $this->assertSame(0, $pool->spare(2, [1 => ['s'], 2 => ['s']], [1 => 1], 1)['spare']);
    }

    public function test_spare_stops_at_what_is_needed(): void
    {
        $pool = new SharedOperatorPool([1 => ['a', 'b', 'c'], 2 => ['a', 'b', 'c']]);
        $present = [1 => ['a', 'b', 'c'], 2 => ['a', 'b', 'c']];

        $this->assertSame(1, $pool->spare(1, $present, [], 1)['spare']);
        $this->assertSame(3, $pool->spare(1, $present, [], 5)['spare']);
        $this->assertSame(2, $pool->spare(1, $present, [2 => 1], 5)['spare']);
    }

    public function test_labor_segments_are_half_open_and_merged(): void
    {
        $t = new Timeline;
        $t->bookLabor(1, 0, 60, 1);
        $t->bookLabor(2, 60, 120, 2);
        $t->bookLabor(2, 120, 180, 2); // same demand straight after: merged
        $t->bookLabor(1, 150, 200, 1);
        $t->bookLabor(3, 0, 500, 9);   // not asked about

        $this->assertSame([
            ['start' => 30, 'end' => 60, 'demand' => [1 => 1]],
            ['start' => 60, 'end' => 150, 'demand' => [2 => 2]],
            ['start' => 150, 'end' => 180, 'demand' => [1 => 1, 2 => 2]],
            ['start' => 180, 'end' => 190, 'demand' => [1 => 1]],
        ], $t->laborSegments([1, 2], 30, 190));

        $this->assertSame([], $t->laborSegments([1, 2], 200, 300), 'a booking ending at the window start does not count');
        $this->assertTrue($t->hasLaborBetween([2], 179, 181));
        $this->assertFalse($t->hasLaborBetween([2], 180, 181));
        $this->assertSame(60, $t->nextLaborReleaseAmong([1, 2], 30));
        $this->assertSame(180, $t->nextLaborReleaseAmong([2], 120));
    }
}
