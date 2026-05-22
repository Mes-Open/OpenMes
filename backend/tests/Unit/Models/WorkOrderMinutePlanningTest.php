<?php

namespace Tests\Unit\Models;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Unit coverage for the minute-level planning helpers on the WorkOrder model.
 * No DB writes occur — instances are constructed in memory — but the Laravel
 * container is booted because Eloquent's datetime casts resolve a database
 * connection during attribute access.
 */
class WorkOrderMinutePlanningTest extends TestCase
{
    public function test_has_minute_planning_returns_false_when_either_field_null(): void
    {
        $unplanned = new WorkOrder([
            'planned_start_at' => null,
            'planned_end_at'   => null,
        ]);
        $this->assertFalse($unplanned->hasMinutePlanning());

        $halfPlanned = new WorkOrder([
            'planned_start_at' => Carbon::parse('2026-05-22 08:00'),
        ]);
        $this->assertFalse($halfPlanned->hasMinutePlanning());

        $endOnly = new WorkOrder([
            'planned_end_at' => Carbon::parse('2026-05-22 10:00'),
        ]);
        $this->assertFalse($endOnly->hasMinutePlanning());
    }

    public function test_has_minute_planning_returns_true_when_both_set(): void
    {
        $wo = new WorkOrder([
            'planned_start_at' => Carbon::parse('2026-05-22 08:00'),
            'planned_end_at'   => Carbon::parse('2026-05-22 10:30'),
        ]);

        $this->assertTrue($wo->hasMinutePlanning());
    }

    public function test_planned_duration_minutes_returns_null_when_not_planned(): void
    {
        $wo = new WorkOrder();

        $this->assertNull($wo->plannedDurationMinutes());
    }

    public function test_planned_duration_minutes_returns_null_when_only_one_end_set(): void
    {
        $wo = new WorkOrder([
            'planned_start_at' => Carbon::parse('2026-05-22 08:00'),
        ]);

        $this->assertNull($wo->plannedDurationMinutes());
    }

    public function test_planned_duration_minutes_computes_correctly(): void
    {
        $wo = new WorkOrder([
            'planned_start_at' => Carbon::parse('2026-05-22 08:00'),
            'planned_end_at'   => Carbon::parse('2026-05-22 10:30'),
        ]);

        $this->assertSame(150, $wo->plannedDurationMinutes());
    }

    public function test_cross_midnight_duration_is_positive(): void
    {
        $wo = new WorkOrder([
            'planned_start_at' => Carbon::parse('2026-05-22 22:00'),
            'planned_end_at'   => Carbon::parse('2026-05-23 06:00'),
        ]);

        $this->assertSame(8 * 60, $wo->plannedDurationMinutes());
    }
}
