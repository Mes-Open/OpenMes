<?php

namespace Tests\Feature;

use App\Models\InspectionPlan;
use App\Models\Material;
use App\Models\User;
use App\Services\Quality\InboundInspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionPlanVersioningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->inspector = User::factory()->create();
        $this->inspector->assignRole('Operator');
    }

    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Bolt QC',
            'description' => 'x',
            'scope' => 'generic',
            'criteria' => [
                ['name' => 'Visual', 'type' => 'pass_fail', 'required' => true],
            ],
        ], $overrides);
    }

    // ── Lifecycle ────────────────────────────────────────────────────────

    public function test_new_plan_is_created_as_draft(): void
    {
        $this->actingAs($this->admin)->post('/admin/inspection-plans', $this->planPayload())
            ->assertRedirect();

        $plan = InspectionPlan::firstWhere('name', 'Bolt QC');
        $this->assertNotNull($plan);
        $this->assertSame(1, $plan->version);
        $this->assertNull($plan->published_at);
        $this->assertFalse($plan->is_active);
        $this->assertNull($plan->root_id);
    }

    public function test_editing_a_draft_updates_in_place(): void
    {
        $plan = InspectionPlan::factory()->draft()->create(['name' => 'Draft P']);

        $this->actingAs($this->admin)->put("/admin/inspection-plans/{$plan->id}", $this->planPayload([
            'name' => 'Draft P edited',
        ]))->assertRedirect();

        $this->assertSame(1, InspectionPlan::count()); // no new version
        $this->assertSame('Draft P edited', $plan->fresh()->name);
        $this->assertSame(1, $plan->fresh()->version);
    }

    public function test_publishing_makes_it_live(): void
    {
        $plan = InspectionPlan::factory()->draft()->create();

        $this->actingAs($this->admin)->post("/admin/inspection-plans/{$plan->id}/publish")
            ->assertRedirect();

        $plan->refresh();
        $this->assertNotNull($plan->published_at);
        $this->assertTrue($plan->is_active);
    }

    public function test_editing_a_published_plan_creates_a_new_draft_version(): void
    {
        $v1 = InspectionPlan::factory()->create(['name' => 'Spec', 'version' => 1]); // published

        $this->actingAs($this->admin)->put("/admin/inspection-plans/{$v1->id}", $this->planPayload([
            'name' => 'Spec v2',
        ]))->assertRedirect();

        $this->assertSame(2, InspectionPlan::count());

        // v1 untouched
        $v1->refresh();
        $this->assertSame('Spec', $v1->name);
        $this->assertNotNull($v1->published_at);

        // v2 is a draft in the same group
        $v2 = InspectionPlan::where('id', '!=', $v1->id)->first();
        $this->assertSame(2, $v2->version);
        $this->assertSame($v1->id, $v2->root_id);
        $this->assertNull($v2->published_at);
        $this->assertFalse($v2->is_active);
        $this->assertSame('Spec v2', $v2->name);
    }

    public function test_publishing_v2_retires_v1(): void
    {
        $v1 = InspectionPlan::factory()->create(['version' => 1]); // published & active
        $v2 = InspectionPlan::factory()->draft()->create(['version' => 2, 'root_id' => $v1->id]);

        $this->actingAs($this->admin)->post("/admin/inspection-plans/{$v2->id}/publish");

        $this->assertFalse($v1->fresh()->is_active);   // archived
        $this->assertTrue($v2->fresh()->is_active);    // live
        $this->assertNotNull($v2->fresh()->published_at);
    }

    // ── Inspections pin the version ──────────────────────────────────────

    public function test_inspection_records_the_plan_version_and_snapshots_criteria(): void
    {
        $material = Material::factory()->create();
        $plan = InspectionPlan::factory()->create([
            'version' => 3,
            'material_id' => $material->id,
            'criteria' => [
                ['name' => 'Length', 'type' => 'measurement', 'unit' => 'mm', 'spec_min' => 1, 'spec_max' => 2, 'required' => true],
            ],
        ]);

        $inspection = app(InboundInspectionService::class)->start(
            $material, 'LOT1', 10, $plan, $this->inspector,
        );

        $this->assertSame(3, $inspection->plan_version);
        $this->assertSame($plan->id, $inspection->inspection_plan_id);

        // Criteria snapshotted into results (reproducible even if plan changes).
        $this->assertCount(1, $inspection->results);
        $this->assertSame('Length', $inspection->results->first()->criterion_name);
    }

    public function test_only_published_active_versions_are_offered_for_inspection(): void
    {
        $material = Material::factory()->create();
        InspectionPlan::factory()->create(['name' => 'Live', 'material_id' => $material->id]);          // published+active
        InspectionPlan::factory()->draft()->create(['name' => 'Draft', 'material_id' => $material->id]); // draft
        $archived = InspectionPlan::factory()->create(['name' => 'Old', 'material_id' => $material->id, 'is_active' => false]);

        $applicable = InspectionPlan::applicableTo($material)->pluck('name')->all();

        $this->assertContains('Live', $applicable);
        $this->assertNotContains('Draft', $applicable);
        $this->assertNotContains('Old', $applicable);
    }

    // ── Deletion guard ───────────────────────────────────────────────────

    public function test_cannot_delete_published_version_with_inspections(): void
    {
        $material = Material::factory()->create();
        $plan = InspectionPlan::factory()->create(['material_id' => $material->id]);
        app(InboundInspectionService::class)->start($material, 'LOT', 1, $plan, $this->inspector);

        $this->actingAs($this->admin)->delete("/admin/inspection-plans/{$plan->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('inspection_plans', ['id' => $plan->id]);
    }

    public function test_draft_can_be_deleted(): void
    {
        $plan = InspectionPlan::factory()->draft()->create();

        $this->actingAs($this->admin)->delete("/admin/inspection-plans/{$plan->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('inspection_plans', ['id' => $plan->id]);
    }

    // ── Authorization ────────────────────────────────────────────────────

    public function test_guest_cannot_publish(): void
    {
        $plan = InspectionPlan::factory()->draft()->create();
        $this->post("/admin/inspection-plans/{$plan->id}/publish")->assertRedirect('/login');
    }

    public function test_operator_cannot_publish(): void
    {
        $plan = InspectionPlan::factory()->draft()->create();
        $this->actingAs($this->inspector)->post("/admin/inspection-plans/{$plan->id}/publish")->assertForbidden();
    }
}
