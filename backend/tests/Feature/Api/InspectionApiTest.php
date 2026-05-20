<?php

namespace Tests\Feature\Api;

use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\Issue;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $inspector;

    private User $operator;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        // Inspectors will be Supervisors (no separate role for this MVP).
        $this->inspector = User::factory()->create();
        $this->inspector->assignRole('Supervisor');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create(['code' => 'M', 'name' => 'Bolt', 'material_type_id' => $type->id]);
    }

    private function asAdmin(): self
    {
        $this->actingAs($this->admin, 'sanctum');
        return $this;
    }

    private function makePlan(): InspectionPlan
    {
        return InspectionPlan::create([
            'name' => 'Bolt incoming',
            'material_id' => $this->material->id,
            'criteria' => [
                ['name' => 'Visual', 'type' => 'pass_fail', 'required' => true],
                ['name' => 'Diameter', 'type' => 'measurement', 'spec_min' => 9.8, 'spec_max' => 10.2, 'required' => true],
            ],
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_inspection_plan(): void
    {
        $response = $this->asAdmin()->postJson('/api/v1/inspection-plans', [
            'name' => 'New plan',
            'material_id' => $this->material->id,
            'criteria' => [
                ['name' => 'Visual', 'type' => 'pass_fail', 'required' => true],
            ],
        ]);
        $response->assertCreated();
        $this->assertDatabaseHas('inspection_plans', ['name' => 'New plan']);
    }

    public function test_operator_cannot_create_inspection_plan(): void
    {
        $response = $this->actingAs($this->operator, 'sanctum')->postJson('/api/v1/inspection-plans', [
            'name' => 'X', 'material_id' => $this->material->id, 'criteria' => [['name' => 'A', 'type' => 'pass_fail']],
        ]);
        $response->assertForbidden();
    }

    public function test_create_plan_rejects_both_material_and_material_type(): void
    {
        $type = MaterialType::create(['code' => 'AUX', 'name' => 'Aux']);
        $response = $this->asAdmin()->postJson('/api/v1/inspection-plans', [
            'name' => 'Bad scope',
            'material_id' => $this->material->id,
            'material_type_id' => $type->id,
            'criteria' => [['name' => 'A', 'type' => 'pass_fail']],
        ]);
        $response->assertStatus(422);
    }

    public function test_create_plan_rejects_invalid_measurement_spec(): void
    {
        $response = $this->asAdmin()->postJson('/api/v1/inspection-plans', [
            'name' => 'Bad spec',
            'material_id' => $this->material->id,
            'criteria' => [
                ['name' => 'D', 'type' => 'measurement', 'spec_min' => 10, 'spec_max' => 5],
            ],
        ]);
        $response->assertStatus(422);
    }

    public function test_inspector_can_start_record_and_complete_with_pass(): void
    {
        $plan = $this->makePlan();

        $start = $this->actingAs($this->inspector, 'sanctum')
            ->postJson('/api/v1/inspections', [
                'material_id' => $this->material->id,
                'lot_number' => 'LOT-AB-001',
                'quantity_received' => 500,
                'inspection_plan_id' => $plan->id,
            ]);
        $start->assertCreated();
        $inspId = $start->json('data.id');
        $results = $start->json('data.results');
        $this->assertCount(2, $results);

        // Record each criterion.
        foreach ($results as $r) {
            $payload = $r['criterion_type'] === 'measurement'
                ? ['value_numeric' => 10.0]
                : ['value_boolean' => true];

            $this->actingAs($this->inspector, 'sanctum')
                ->patchJson("/api/v1/inspections/{$inspId}/results/{$r['id']}", $payload)
                ->assertOk();
        }

        $complete = $this->actingAs($this->inspector, 'sanctum')
            ->postJson("/api/v1/inspections/{$inspId}/complete");
        $complete->assertOk();
        $this->assertSame('pass', $complete->json('data.status'));
        $this->assertNull($complete->json('data.issue_id'));
    }

    public function test_complete_with_fail_creates_linked_non_conformance(): void
    {
        $plan = $this->makePlan();
        $inspection = (new \App\Services\Quality\InboundInspectionService())
            ->start($this->material, 'LOT-FAIL', 100, $plan, $this->inspector);

        foreach ($inspection->results as $r) {
            $payload = $r->criterion_type === 'measurement'
                ? ['value_numeric' => 99.0]  // way out of spec
                : ['value_boolean' => false];
            $this->actingAs($this->inspector, 'sanctum')
                ->patchJson("/api/v1/inspections/{$inspection->id}/results/{$r->id}", $payload);
        }

        $complete = $this->actingAs($this->inspector, 'sanctum')
            ->postJson("/api/v1/inspections/{$inspection->id}/complete");

        $complete->assertOk();
        $this->assertSame('fail', $complete->json('data.status'));
        $issueId = $complete->json('data.issue_id');
        $this->assertNotNull($issueId);

        $issue = Issue::find($issueId);
        $this->assertSame(Issue::SOURCE_INBOUND_INSPECTION, $issue->source);
        $this->assertNull($issue->work_order_id);
    }

    public function test_cannot_edit_results_after_completion(): void
    {
        $plan = $this->makePlan();
        $inspection = (new \App\Services\Quality\InboundInspectionService())
            ->start($this->material, 'LOT-DONE', 10, $plan, $this->inspector);
        foreach ($inspection->results as $r) {
            (new \App\Services\Quality\InboundInspectionService())->recordResult($r, ['value_boolean' => true, 'value_numeric' => 10.0]);
        }
        (new \App\Services\Quality\InboundInspectionService())->complete($inspection);

        $firstResult = $inspection->refresh()->results->first();

        $response = $this->actingAs($this->inspector, 'sanctum')
            ->patchJson("/api/v1/inspections/{$inspection->id}/results/{$firstResult->id}", ['value_boolean' => false]);
        $response->assertStatus(422);
    }

    public function test_index_filters_by_material_lot_status(): void
    {
        Inspection::factory()->failed()->create(['material_id' => $this->material->id, 'lot_number' => 'L1']);
        Inspection::factory()->passed()->create(['material_id' => $this->material->id, 'lot_number' => 'L2']);
        Inspection::factory()->pending()->create(['material_id' => $this->material->id, 'lot_number' => 'L3']);

        $r = $this->asAdmin()->getJson('/api/v1/inspections?status=fail');
        $this->assertCount(1, $r->json('data'));

        $r2 = $this->asAdmin()->getJson('/api/v1/inspections?lot_number=L2');
        $this->assertCount(1, $r2->json('data'));
        $this->assertSame('pass', $r2->json('data.0.status'));
    }

    public function test_stats_endpoint_computes_pass_rate(): void
    {
        Inspection::factory()->count(3)->passed()->create(['material_id' => $this->material->id]);
        Inspection::factory()->failed()->create(['material_id' => $this->material->id]);

        $r = $this->asAdmin()->getJson('/api/v1/inspections/stats?days=30');
        $r->assertOk();
        $this->assertSame(4, $r->json('data.total_completed'));
        $this->assertSame(3, $r->json('data.pass_count'));
        $this->assertEqualsWithDelta(75.0, $r->json('data.pass_rate'), 0.1);
    }

    public function test_unauthenticated_access_denied(): void
    {
        $this->getJson('/api/v1/inspections')->assertUnauthorized();
        $this->postJson('/api/v1/inspections', [])->assertUnauthorized();
    }
}
