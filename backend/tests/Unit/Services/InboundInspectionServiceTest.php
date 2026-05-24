<?php

namespace Tests\Unit\Services;

use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\InspectionResult;
use App\Models\Issue;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use App\Services\Quality\InboundInspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InboundInspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private InboundInspectionService $service;

    private Material $material;

    private User $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);

        $this->service = app(InboundInspectionService::class);
        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'M-001',
            'name' => 'Steel bolt M10',
            'material_type_id' => $type->id,
        ]);
        $this->inspector = User::factory()->create();
    }

    private function planWith(array $criteria): InspectionPlan
    {
        return InspectionPlan::create([
            'name' => 'Steel bolt incoming',
            'material_id' => $this->material->id,
            'criteria' => $criteria,
            'is_active' => true,
        ]);
    }

    public function test_start_snapshots_plan_criteria_to_results(): void
    {
        $plan = $this->planWith([
            ['name' => 'Visual', 'type' => 'pass_fail', 'required' => true],
            ['name' => 'Diameter', 'type' => 'measurement', 'unit' => 'mm', 'spec_min' => 9.8, 'spec_max' => 10.2, 'required' => true],
        ]);

        $inspection = $this->service->start($this->material, 'LOT-001', 500.0, $plan, $this->inspector);

        $this->assertCount(2, $inspection->results);
        $this->assertSame('Visual', $inspection->results[0]->criterion_name);
        $this->assertSame('measurement', $inspection->results[1]->criterion_type);
        $this->assertEqualsWithDelta(9.8, (float) $inspection->results[1]->spec_min, 0.0001);
    }

    public function test_complete_marks_pass_when_all_required_pass(): void
    {
        $plan = $this->planWith([
            ['name' => 'Visual', 'type' => 'pass_fail', 'required' => true],
            ['name' => 'Diameter', 'type' => 'measurement', 'unit' => 'mm', 'spec_min' => 9.8, 'spec_max' => 10.2, 'required' => true],
        ]);
        $insp = $this->service->start($this->material, 'L1', 100.0, $plan, $this->inspector);

        $this->service->recordResult($insp->results[0], ['value_boolean' => true]);
        $this->service->recordResult($insp->results[1], ['value_numeric' => 10.0]);

        $completed = $this->service->complete($insp);

        $this->assertSame(Inspection::STATUS_PASS, $completed->status);
        $this->assertNull($completed->issue_id);
    }

    public function test_complete_creates_non_conformance_on_fail(): void
    {
        $plan = $this->planWith([
            ['name' => 'Diameter', 'type' => 'measurement', 'spec_min' => 9.8, 'spec_max' => 10.2, 'required' => true],
        ]);
        $insp = $this->service->start($this->material, 'L2', 100.0, $plan, $this->inspector);

        // Out-of-spec measurement.
        $this->service->recordResult($insp->results[0], ['value_numeric' => 11.5]);

        $completed = $this->service->complete($insp);

        $this->assertSame(Inspection::STATUS_FAIL, $completed->status);
        $this->assertNotNull($completed->issue_id);

        $issue = Issue::find($completed->issue_id);
        $this->assertNotNull($issue);
        $this->assertSame(Issue::SOURCE_INBOUND_INSPECTION, $issue->source);
        $this->assertSame($this->material->id, $issue->material_id);
        $this->assertNull($issue->work_order_id, 'inbound NC must not require a WO');
        $this->assertStringContainsString('Diameter', $issue->description);
    }

    public function test_conditional_pass_when_only_optional_fails(): void
    {
        $plan = $this->planWith([
            ['name' => 'Critical visual', 'type' => 'pass_fail', 'required' => true],
            ['name' => 'Cosmetic flaw', 'type' => 'pass_fail', 'required' => false],
        ]);
        $insp = $this->service->start($this->material, 'L3', 50.0, $plan, $this->inspector);

        $this->service->recordResult($insp->results[0], ['value_boolean' => true]);
        $this->service->recordResult($insp->results[1], ['value_boolean' => false]);

        $completed = $this->service->complete($insp);

        $this->assertSame(Inspection::STATUS_CONDITIONAL, $completed->status);
        $this->assertNull($completed->issue_id, 'conditional pass should not create NC');
    }

    public function test_required_criterion_left_unrecorded_results_in_fail(): void
    {
        $plan = $this->planWith([
            ['name' => 'Visual', 'type' => 'pass_fail', 'required' => true],
            ['name' => 'Diameter', 'type' => 'measurement', 'required' => true, 'spec_min' => 9.8, 'spec_max' => 10.2],
        ]);
        $insp = $this->service->start($this->material, 'L4', 10.0, $plan, $this->inspector);

        // Only the first criterion recorded.
        $this->service->recordResult($insp->results[0], ['value_boolean' => true]);

        $completed = $this->service->complete($insp);

        $this->assertSame(Inspection::STATUS_FAIL, $completed->status);
    }

    public function test_cannot_complete_inspection_twice(): void
    {
        $plan = $this->planWith([
            ['name' => 'Visual', 'type' => 'pass_fail', 'required' => true],
        ]);
        $insp = $this->service->start($this->material, 'L5', 10.0, $plan, $this->inspector);
        $this->service->recordResult($insp->results[0], ['value_boolean' => true]);
        $this->service->complete($insp);

        $this->expectException(RuntimeException::class);
        $this->service->complete($insp->refresh());
    }

    public function test_measurement_within_spec_passes(): void
    {
        $r = new InspectionResult([
            'criterion_type' => 'measurement',
            'spec_min' => 5.0,
            'spec_max' => 10.0,
            'value_numeric' => 7.5,
        ]);
        $this->assertTrue($r->evaluate());
    }

    public function test_measurement_below_min_fails(): void
    {
        $r = new InspectionResult([
            'criterion_type' => 'measurement',
            'spec_min' => 5.0,
            'spec_max' => 10.0,
            'value_numeric' => 4.9,
        ]);
        $this->assertFalse($r->evaluate());
    }

    public function test_measurement_above_max_fails(): void
    {
        $r = new InspectionResult([
            'criterion_type' => 'measurement',
            'spec_min' => 5.0,
            'spec_max' => 10.0,
            'value_numeric' => 10.1,
        ]);
        $this->assertFalse($r->evaluate());
    }

    public function test_pass_fail_evaluates_value_boolean(): void
    {
        $r = new InspectionResult(['criterion_type' => 'pass_fail', 'value_boolean' => false]);
        $this->assertFalse($r->evaluate());

        $r2 = new InspectionResult(['criterion_type' => 'pass_fail', 'value_boolean' => true]);
        $this->assertTrue($r2->evaluate());
    }
}
