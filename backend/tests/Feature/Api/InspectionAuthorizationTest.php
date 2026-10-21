<?php

namespace Tests\Feature\Api;

use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\User;
use App\Services\Quality\InboundInspectionService;
use App\Support\InspectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /api/v1/inspections is authorised by InspectionPolicy, like the operator
 * screen: the "Quality inspector" permission to carry an inspection out, the
 * higher "Quality disposition" one to decide on — and so release — the lot.
 * Before this an operator token could start, fill in and complete an
 * inspection, and completing one stocked a released lot in.
 */
class InspectionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    private InspectionPlan $plan;

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
            'criteria' => [['name' => 'Visual', 'type' => 'pass_fail', 'required' => true]],
            'is_active' => true,
        ]);

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(true)],
        );
    }

    private function operator(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->assignRole('Operator');
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    private function supervisor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        return $user;
    }

    /** A pending inspection with its single criterion recorded as passed. */
    private function recordedInspection(?User $inspector = null): Inspection
    {
        $svc = app(InboundInspectionService::class);
        $inspection = $svc->start($this->material, 'LOT-1', 80, $this->plan, $inspector ?? $this->supervisor());
        $svc->recordResult($inspection->results->first(), ['value_boolean' => true]);

        return $inspection->fresh('results');
    }

    private function startPayload(): array
    {
        return [
            'material_id' => $this->material->id,
            'lot_number' => 'LOT-NEW',
            'quantity_received' => 50,
            'inspection_plan_id' => $this->plan->id,
        ];
    }

    public function test_guest_gets_401_everywhere(): void
    {
        $inspection = $this->recordedInspection();
        $result = $inspection->results->first();

        $this->getJson('/api/v1/inspections')->assertUnauthorized();
        $this->getJson('/api/v1/inspections/stats')->assertUnauthorized();
        $this->getJson("/api/v1/inspections/{$inspection->id}")->assertUnauthorized();
        $this->postJson('/api/v1/inspections', $this->startPayload())->assertUnauthorized();
        $this->patchJson("/api/v1/inspections/{$inspection->id}/results/{$result->id}", ['value_boolean' => true])->assertUnauthorized();
        $this->postJson("/api/v1/inspections/{$inspection->id}/complete")->assertUnauthorized();
        $this->postJson("/api/v1/inspections/{$inspection->id}/disposition", ['disposition' => 'accept'])->assertUnauthorized();
    }

    public function test_operator_without_permission_is_refused_everything(): void
    {
        $operator = $this->operator();
        $inspection = $this->recordedInspection();
        $result = $inspection->results->first();
        $this->actingAs($operator, 'sanctum');

        $this->getJson('/api/v1/inspections')->assertForbidden();
        $this->getJson('/api/v1/inspections/stats')->assertForbidden();
        $this->getJson("/api/v1/inspections/{$inspection->id}")->assertForbidden();
        $this->postJson('/api/v1/inspections', $this->startPayload())->assertForbidden();
        $this->patchJson("/api/v1/inspections/{$inspection->id}/results/{$result->id}", ['value_boolean' => false])->assertForbidden();
        $this->postJson("/api/v1/inspections/{$inspection->id}/complete")->assertForbidden();
        $this->postJson("/api/v1/inspections/{$inspection->id}/disposition", ['disposition' => 'accept'])->assertForbidden();

        $this->assertDatabaseMissing('inspections', ['lot_number' => 'LOT-NEW']);
        $this->assertTrue($result->fresh()->value_boolean, 'The refused write must not change the result.');
        $this->assertSame(Inspection::STATUS_PENDING, $inspection->fresh()->status);
        $this->assertSame(0, MaterialLot::count());
    }

    public function test_inspector_carries_out_an_inspection_but_cannot_release_the_lot(): void
    {
        $inspector = $this->operator(InspectionPermissions::PERFORM);
        $this->actingAs($inspector, 'sanctum');

        $this->getJson('/api/v1/inspections')->assertOk();

        $start = $this->postJson('/api/v1/inspections', $this->startPayload())->assertCreated();
        $id = $start->json('data.id');
        $resultId = $start->json('data.results.0.id');

        $this->patchJson("/api/v1/inspections/{$id}/results/{$resultId}", ['value_boolean' => true])->assertOk();
        $this->postJson("/api/v1/inspections/{$id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Inspection::STATUS_PASS);

        // Completing did not stock anything in…
        $this->assertSame(0, MaterialLot::where('inspection_id', $id)->count());

        // …and the inspector cannot release it themselves.
        $this->postJson("/api/v1/inspections/{$id}/disposition", ['disposition' => 'accept'])->assertForbidden();
        $this->assertSame(0, MaterialLot::where('inspection_id', $id)->count());
        $this->assertSame(Inspection::DISPOSITION_PENDING, Inspection::find($id)->disposition);
    }

    public function test_disposition_holder_releases_an_inspectors_lot(): void
    {
        $inspector = $this->operator(InspectionPermissions::PERFORM);
        $inspection = $this->recordedInspection($inspector);
        $this->actingAs($inspector, 'sanctum')->postJson("/api/v1/inspections/{$inspection->id}/complete")->assertOk();

        $decider = $this->operator(InspectionPermissions::DISPOSITION);
        $this->actingAs($decider, 'sanctum')
            ->postJson("/api/v1/inspections/{$inspection->id}/disposition", ['disposition' => 'accept'])
            ->assertOk()
            ->assertJsonPath('data.disposition', Inspection::DISPOSITION_ACCEPT);

        $lot = MaterialLot::firstWhere('inspection_id', $inspection->id);
        $this->assertNotNull($lot);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->status);
        $this->assertEqualsWithDelta(80.0, (float) $lot->quantity_available, 0.0001);
    }

    public function test_disposition_holder_can_also_carry_out_an_inspection(): void
    {
        $decider = $this->operator(InspectionPermissions::DISPOSITION);
        $this->actingAs($decider, 'sanctum');

        $start = $this->postJson('/api/v1/inspections', $this->startPayload())->assertCreated();
        $id = $start->json('data.id');
        $this->patchJson("/api/v1/inspections/{$id}/results/{$start->json('data.results.0.id')}", ['value_boolean' => true])->assertOk();
        $this->postJson("/api/v1/inspections/{$id}/complete")->assertOk();

        // Someone who may decide on the lot puts it on record by completing, as before.
        $this->assertSame(MaterialLot::STATUS_RELEASED, MaterialLot::firstWhere('inspection_id', $id)?->status);
    }

    public function test_quarantine_decision_on_an_inspectors_lot_holds_it(): void
    {
        $inspector = $this->operator(InspectionPermissions::PERFORM);
        $inspection = $this->recordedInspection($inspector);
        $this->actingAs($inspector, 'sanctum')->postJson("/api/v1/inspections/{$inspection->id}/complete")->assertOk();

        $this->actingAs($this->supervisor(), 'sanctum')
            ->postJson("/api/v1/inspections/{$inspection->id}/disposition", ['disposition' => 'quarantine'])
            ->assertOk();

        $lot = MaterialLot::firstWhere('inspection_id', $inspection->id);
        $this->assertSame(MaterialLot::STATUS_QUARANTINE, $lot->status);
        $this->assertEqualsWithDelta(0.0, (float) $lot->quantity_available, 0.0001);
    }

    public function test_supervisor_keeps_both_rights_from_the_seeder(): void
    {
        $supervisor = $this->supervisor();
        $inspection = $this->recordedInspection($supervisor);

        $this->actingAs($supervisor, 'sanctum')->postJson("/api/v1/inspections/{$inspection->id}/complete")->assertOk();
        $this->assertSame(MaterialLot::STATUS_RELEASED, MaterialLot::firstWhere('inspection_id', $inspection->id)?->status);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/inspections/{$inspection->id}/disposition", ['disposition' => 'accept'])
            ->assertOk();
    }

    public function test_validation_errors_are_422_for_a_permitted_user(): void
    {
        $decider = $this->operator(InspectionPermissions::DISPOSITION);
        $inspection = $this->recordedInspection();
        $this->actingAs($decider, 'sanctum');

        $this->postJson('/api/v1/inspections', ['lot_number' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('material_id');
        $this->postJson('/api/v1/inspections', ['material_id' => $this->material->id, 'lot_number' => str_repeat('x', 101)])
            ->assertStatus(422)->assertJsonValidationErrors('lot_number');
        $this->postJson("/api/v1/inspections/{$inspection->id}/disposition", ['disposition' => 'pending'])
            ->assertStatus(422)->assertJsonValidationErrors('disposition');
        $this->postJson("/api/v1/inspections/{$inspection->id}/disposition", ['disposition' => 'nonsense'])
            ->assertStatus(422)->assertJsonValidationErrors('disposition');
        $this->patchJson("/api/v1/inspections/{$inspection->id}/results/{$inspection->results->first()->id}", ['value_numeric' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors('value_numeric');
    }

    public function test_role_level_inspector_grant_works_like_a_personal_one(): void
    {
        // A role granted the inspector right through Settings → Access works
        // the same as a person granted it directly.
        \Spatie\Permission\Models\Role::findByName('Operator', 'web')->givePermissionTo(InspectionPermissions::PERFORM);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->operator(), 'sanctum')
            ->postJson('/api/v1/inspections', $this->startPayload())
            ->assertCreated();
    }
}
