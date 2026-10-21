<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\User;
use App\Services\Quality\InboundInspectionService;
use App\Support\InspectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The inspection screen on the operator tablet. Same InspectionPolicy as the
 * API: an operator needs "Quality inspector" to carry an inspection out, and
 * "Quality disposition" to release the lot it covers.
 */
class OperatorInspectionsTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    private InspectionPlan $plan;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create(['code' => 'M', 'name' => 'Bolt', 'material_type_id' => $type->id, 'unit_of_measure' => 'pcs']);
        $this->plan = InspectionPlan::create([
            'name' => 'Bolt incoming',
            'material_id' => $this->material->id,
            'criteria' => [
                ['name' => 'Visual', 'type' => 'pass_fail', 'required' => true],
                ['name' => 'Diameter', 'type' => 'measurement', 'spec_min' => 9.8, 'spec_max' => 10.2, 'required' => true],
            ],
            'is_active' => true,
            'published_at' => now(),
        ]);
        $this->line = Line::factory()->create(['is_active' => true]);

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(true)],
        );
    }

    private function operator(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->assignRole('Operator');
        $user->lines()->attach($this->line->id);
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    /** A completed, passed inspection nobody has decided on yet. */
    private function completedInspection(User $inspector): Inspection
    {
        $svc = app(InboundInspectionService::class);
        $inspection = $svc->start($this->material, 'LOT-DONE', 30, $this->plan, $inspector);
        foreach ($inspection->results as $result) {
            $svc->recordResult($result, ['value_boolean' => true, 'value_numeric' => 10.0]);
        }

        return $svc->complete($inspection->fresh('results'), null, $inspector);
    }

    private function tabKeys(User $user): array
    {
        $this->app->forgetScopedInstances();
        $response = $this->actingAs($user)->get("/operator/queue?line={$this->line->id}&workstation=all")->assertOk();

        return collect($response->viewData('page')['props']['operatorTabs'])->pluck('key')->all();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $inspection = $this->completedInspection($this->operator(InspectionPermissions::PERFORM));

        $this->get('/operator/inspections')->assertRedirect('/login');
        $this->get("/operator/inspections/{$inspection->id}")->assertRedirect('/login');
        $this->post('/operator/inspections', [])->assertRedirect('/login');
        $this->post("/operator/inspections/{$inspection->id}/disposition", ['disposition' => 'accept'])->assertRedirect('/login');
        $this->assertSame(0, MaterialLot::count());
    }

    public function test_operator_without_permission_is_refused_and_sees_no_tab(): void
    {
        $operator = $this->operator();
        $svc = app(InboundInspectionService::class);
        $inspection = $svc->start($this->material, 'LOT-P', 10, $this->plan, $this->operator(InspectionPermissions::PERFORM));
        $result = $inspection->results->first();

        $this->assertNotContains('inspections', $this->tabKeys($operator));

        $this->actingAs($operator);
        $this->get('/operator/inspections')->assertForbidden();
        $this->get("/operator/inspections/{$inspection->id}")->assertForbidden();
        $this->post('/operator/inspections', ['material_id' => $this->material->id, 'lot_number' => 'LOT-X'])->assertForbidden();
        $this->patch("/operator/inspections/{$inspection->id}/results/{$result->id}", ['value_boolean' => true])->assertForbidden();
        $this->post("/operator/inspections/{$inspection->id}/complete")->assertForbidden();
        $this->post("/operator/inspections/{$inspection->id}/disposition", ['disposition' => 'accept'])->assertForbidden();

        $this->assertDatabaseMissing('inspections', ['lot_number' => 'LOT-X']);
        $this->assertNull($result->fresh()->value_boolean);
        $this->assertSame(Inspection::STATUS_PENDING, $inspection->fresh()->status);
    }

    public function test_inspector_sees_the_tab_and_carries_out_an_inspection_without_releasing_it(): void
    {
        $inspector = $this->operator(InspectionPermissions::PERFORM);

        $this->assertContains('inspections', $this->tabKeys($inspector));

        $this->actingAs($inspector)->get("/operator/inspections?line={$this->line->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operator/inspections/Index')
                ->where('can.perform', true)
                ->where('can.disposition', false)
                ->where('line.id', $this->line->id)
                ->has('materials', 1));

        // No plan chosen: the one that applies to the material is used.
        $this->post('/operator/inspections', [
            'material_id' => $this->material->id,
            'lot_number' => 'LOT-TAB',
            'quantity_received' => 25,
        ])->assertRedirect()->assertSessionHas('success');

        $inspection = Inspection::where('lot_number', 'LOT-TAB')->firstOrFail();
        $this->assertSame($this->plan->id, $inspection->inspection_plan_id);
        $this->assertCount(2, $inspection->results);

        foreach ($inspection->results as $result) {
            $payload = $result->criterion_type === 'measurement' ? ['value_numeric' => 10.0] : ['value_boolean' => true];
            $this->patch("/operator/inspections/{$inspection->id}/results/{$result->id}", $payload)
                ->assertRedirect()->assertSessionHas('success');
        }

        $this->post("/operator/inspections/{$inspection->id}/complete")->assertRedirect()->assertSessionHas('success');
        $this->assertSame(Inspection::STATUS_PASS, $inspection->fresh()->status);
        $this->assertSame(0, MaterialLot::count(), 'Completing must not stock anything in for an inspector.');

        $this->get("/operator/inspections/{$inspection->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operator/inspections/Show')
                ->where('inspection.status', Inspection::STATUS_PASS)
                ->where('can.disposition', false));

        $this->post("/operator/inspections/{$inspection->id}/disposition", ['disposition' => 'accept'])->assertForbidden();
        $this->assertSame(0, MaterialLot::count());
        $this->assertSame(Inspection::DISPOSITION_PENDING, $inspection->fresh()->disposition);
    }

    public function test_disposition_holder_releases_the_lot(): void
    {
        $inspection = $this->completedInspection($this->operator(InspectionPermissions::PERFORM));
        $decider = $this->operator(InspectionPermissions::DISPOSITION);

        $this->actingAs($decider)->get('/operator/inspections')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.disposition', true)
                ->where('awaitingDisposition.0.id', $inspection->id));

        $this->post("/operator/inspections/{$inspection->id}/disposition", ['disposition' => 'accept'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(Inspection::DISPOSITION_ACCEPT, $inspection->fresh()->disposition);
        $lot = MaterialLot::firstWhere('inspection_id', $inspection->id);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot?->status);
        $this->assertEqualsWithDelta(30.0, (float) $lot->quantity_available, 0.0001);
    }

    public function test_invalid_input_is_a_validation_error(): void
    {
        $inspector = $this->operator(InspectionPermissions::DISPOSITION);
        $inspection = $this->completedInspection($inspector);

        $this->actingAs($inspector)
            ->post('/operator/inspections', ['material_id' => $this->material->id, 'lot_number' => ''])
            ->assertSessionHasErrors('lot_number');
        $this->post('/operator/inspections', ['material_id' => 999999, 'lot_number' => 'L'])
            ->assertSessionHasErrors('material_id');
        $this->post("/operator/inspections/{$inspection->id}/disposition", ['disposition' => 'pending'])
            ->assertSessionHasErrors('disposition');
        $this->assertSame(Inspection::DISPOSITION_PENDING, $inspection->fresh()->disposition);
    }

    public function test_results_of_a_completed_inspection_cannot_be_changed(): void
    {
        $inspector = $this->operator(InspectionPermissions::PERFORM);
        $inspection = $this->completedInspection($inspector);
        $result = $inspection->results->first();

        $this->actingAs($inspector)
            ->patch("/operator/inspections/{$inspection->id}/results/{$result->id}", ['value_boolean' => false])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertTrue($result->fresh()->value_boolean);
    }
}
