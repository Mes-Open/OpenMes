<?php

namespace Tests\Unit\Models;

use App\Models\InspectionPlan;
use PHPUnit\Framework\TestCase;

class InspectionPlanCriteriaTest extends TestCase
{
    public function test_bare_strings_become_required_pass_fail_checks(): void
    {
        $this->assertSame([
            ['name' => 'Mill certificate', 'type' => 'pass_fail', 'required' => true],
            ['name' => 'Diameter tolerance', 'type' => 'pass_fail', 'required' => true],
        ], InspectionPlan::normalizeCriteria(['mill_certificate', 'diameter-tolerance', '  ']));
    }

    public function test_structured_criteria_pass_through_unchanged(): void
    {
        $criterion = ['name' => 'Diameter', 'type' => 'measurement', 'spec_min' => 9.8, 'spec_max' => 10.2, 'required' => true];

        $this->assertSame([$criterion], InspectionPlan::normalizeCriteria([$criterion]));
        $this->assertSame([], InspectionPlan::normalizeCriteria(null));
    }
}
