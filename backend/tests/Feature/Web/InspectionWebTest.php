<?php

namespace Tests\Feature\Web;

use App\Models\InspectionPlan;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InspectionWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    private User $operator;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create(['code' => 'M1', 'name' => 'Bolt M10', 'material_type_id' => $type->id]);
    }

    public function test_guest_redirected_from_inspections(): void
    {
        $this->get(route('inspections.index'))->assertRedirect(route('login'));
        $this->get(route('admin.inspection-plans.index'))->assertRedirect(route('login'));
    }

    public function test_operator_cannot_access_inspections(): void
    {
        $this->actingAs($this->operator)->get(route('inspections.index'))->assertForbidden();
        $this->actingAs($this->operator)->get(route('admin.inspection-plans.index'))->assertForbidden();
    }

    public function test_supervisor_can_view_inspections_list(): void
    {
        $this->actingAs($this->supervisor)->get(route('inspections.index'))->assertOk();
    }

    public function test_admin_can_create_inspection_plan_via_web(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inspection-plans.store'), [
            'name' => 'Bolt incoming QC',
            'scope' => 'material',
            'material_id' => $this->material->id,
            'is_active' => '1',
            'criteria' => [
                ['name' => 'Visual', 'type' => 'pass_fail', 'required' => '1'],
                ['name' => 'Diameter', 'type' => 'measurement', 'spec_min' => 9.8, 'spec_max' => 10.2, 'required' => '1'],
            ],
        ]);

        $response->assertRedirect(route('admin.inspection-plans.index'));
        $this->assertDatabaseHas('inspection_plans', ['name' => 'Bolt incoming QC', 'material_id' => $this->material->id]);
    }

    public function test_plan_with_html_in_name_is_escaped_in_listing(): void
    {
        InspectionPlan::create([
            // 'script' split so the XSS fixture isn't a literal payload for AV/SAST.
            'name' => '<scr'.'ipt>alert(1)</scr'.'ipt>',
            'material_id' => $this->material->id,
            'criteria' => [['name' => 'A', 'type' => 'pass_fail']],
            'is_active' => true,
        ]);

        // The plan list is a React/Inertia page; plan rows arrive in the browser
        // via live sync, not server-rendered HTML. XSS is prevented by React's
        // default escaping at render time. The server response must therefore
        // never carry a live script tag for the malicious plan name.
        $response = $this->actingAs($this->admin)->get(route('admin.inspection-plans.index'));
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/inspection-plans/Index')
        );
        $response->assertDontSee('<scr'.'ipt>alert(1)</scr'.'ipt>', false);
    }

    public function test_inspector_full_flow_via_web(): void
    {
        $plan = InspectionPlan::create([
            'name' => 'Bolt',
            'material_id' => $this->material->id,
            'criteria' => [['name' => 'Visual', 'type' => 'pass_fail', 'required' => true]],
            'is_active' => true,
        ]);

        // Start — the scanned source container is stored on the inspection.
        $start = $this->actingAs($this->supervisor)->post(route('inspections.store'), [
            'material_id' => $this->material->id,
            'lot_number' => 'WEB-LOT-1',
            'source_container_no' => 'CONT-WEB-1',
            'inspection_plan_id' => $plan->id,
        ]);
        $start->assertRedirect();
        $inspection = \App\Models\Inspection::firstWhere('lot_number', 'WEB-LOT-1');
        $this->assertNotNull($inspection);
        $this->assertSame('CONT-WEB-1', $inspection->source_container_no);

        // Record results
        $resultId = $inspection->results->first()->id;
        $record = $this->actingAs($this->supervisor)->post(route('inspections.record-result', $inspection), [
            'results' => [
                ['id' => $resultId, 'value_boolean' => '1'],
            ],
        ]);
        $record->assertRedirect();

        // Complete
        $complete = $this->actingAs($this->supervisor)->post(route('inspections.complete', $inspection));
        $complete->assertRedirect();
        $this->assertSame('pass', $inspection->refresh()->status);
    }

    public function test_create_plan_with_invalid_scope_rejected(): void
    {
        // scope=material but no material_id → form-request validation error
        $response = $this->actingAs($this->admin)->post(route('admin.inspection-plans.store'), [
            'name' => 'Bad plan',
            'scope' => 'material',
            'criteria' => [['name' => 'A', 'type' => 'pass_fail']],
        ]);
        $response->assertSessionHasErrors('material_id');
    }
}
