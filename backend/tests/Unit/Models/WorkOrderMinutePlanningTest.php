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
            'planned_end_at' => null,
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
            'planned_end_at' => Carbon::parse('2026-05-22 10:30'),
        ]);

        $this->assertTrue($wo->hasMinutePlanning());
    }

    public function test_estimated_standard_production_minutes_sums_setup_plus_run_times_qty(): void
    {
        $wo = new WorkOrder([
            'planned_qty' => 10,
            'process_snapshot' => [
                'steps' => [
                    ['setup_time_minutes' => 15, 'run_time_per_unit_minutes' => 2],   // 15 + 2*10 = 35
                    ['setup_time_minutes' => 5, 'run_time_per_unit_minutes' => null], //  5 + 0    =  5
                    ['estimated_duration_minutes' => 99],                             // no standard → skipped
                ],
            ],
        ]);

        $this->assertSame(40, $wo->estimatedStandardProductionMinutes());
    }

    public function test_estimated_standard_production_minutes_excludes_unselected_variants(): void
    {
        $wo = new WorkOrder([
            'planned_qty' => 10,
            'process_snapshot' => [
                'steps' => [
                    // Variant group "finish": step 2 is the marked default → selected.
                    ['step_number' => 1, 'variant_group' => 'finish', 'is_default_variant' => false,
                        'setup_time_minutes' => 10, 'run_time_per_unit_minutes' => 2],  // skipped
                    ['step_number' => 2, 'variant_group' => 'finish', 'is_default_variant' => true,
                        'setup_time_minutes' => 20, 'run_time_per_unit_minutes' => 3],  // 20 + 3*10 = 50
                    // Non-variant step always counts.
                    ['step_number' => 3, 'variant_group' => null,
                        'setup_time_minutes' => 5, 'run_time_per_unit_minutes' => 1],   //  5 + 1*10 = 15
                ],
            ],
        ]);

        // Only the selected default variant (50) + the plain step (15) = 65.
        $this->assertSame(65, $wo->estimatedStandardProductionMinutes());
    }

    public function test_estimated_standard_production_minutes_variant_defaults_to_lowest_step_number(): void
    {
        $wo = new WorkOrder([
            'planned_qty' => 10,
            'process_snapshot' => [
                'steps' => [
                    // No default flagged → lowest step_number (1) in the group is selected.
                    ['step_number' => 1, 'variant_group' => 'finish', 'is_default_variant' => false,
                        'setup_time_minutes' => 10, 'run_time_per_unit_minutes' => 2],  // 10 + 2*10 = 30
                    ['step_number' => 2, 'variant_group' => 'finish', 'is_default_variant' => false,
                        'setup_time_minutes' => 20, 'run_time_per_unit_minutes' => 3],  // skipped
                ],
            ],
        ]);

        $this->assertSame(30, $wo->estimatedStandardProductionMinutes());
    }

    public function test_estimated_standard_production_minutes_null_without_standard_times(): void
    {
        $wo = new WorkOrder([
            'planned_qty' => 10,
            'process_snapshot' => ['steps' => [['estimated_duration_minutes' => 30]]],
        ]);

        $this->assertNull($wo->estimatedStandardProductionMinutes());
    }

    public function test_planned_duration_minutes_returns_null_when_not_planned(): void
    {
        $wo = new WorkOrder;

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
            'planned_end_at' => Carbon::parse('2026-05-22 10:30'),
        ]);

        $this->assertSame(150, $wo->plannedDurationMinutes());
    }

    public function test_cross_midnight_duration_is_positive(): void
    {
        $wo = new WorkOrder([
            'planned_start_at' => Carbon::parse('2026-05-22 22:00'),
            'planned_end_at' => Carbon::parse('2026-05-23 06:00'),
        ]);

        $this->assertSame(8 * 60, $wo->plannedDurationMinutes());
    }
}
