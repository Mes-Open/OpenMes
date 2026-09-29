<?php

namespace Tests\Feature\Api\V1;

use App\Models\EmployeeActivity;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * API endpoints that reach into the optional workforce and structure modules.
 *
 * Each of these had a counterpart in the web tree that guards the same thing —
 * in two cases with a comment explaining that not guarding it is a 500 — and
 * each API copy went without. Nothing here is subtle; it is the same pattern,
 * applied where it was missed.
 */
class ModuleBoundaryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole('Admin');
    }

    public function test_the_team_day_tacho_answers_without_the_personnel_module(): void
    {
        // personnelClass was eager-loaded unconditionally, so this endpoint was
        // a 500 on every call from a community installation.
        Worker::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/employee-activities/team-day');

        $response->assertOk();
    }

    public function test_the_production_cost_report_answers_without_the_wage_module(): void
    {
        // The web twin of this report has guarded wageGroup since it was
        // written, with a comment saying why. This copy did not.
        //
        // The report must have something to report on: with no completed order
        // the nested eager load never resolves and the endpoint answers 200
        // whether or not it is guarded. Written without this, the test passed
        // against the unfixed code.
        $order = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_DONE,
            'completed_at' => now()->subDay(),
        ]);
        EmployeeActivity::factory()->create([
            'work_order_id' => $order->id,
            'worker_id' => Worker::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/production-cost');

        $response->assertOk();
    }

    public function test_a_division_cannot_be_assigned_without_the_structure_module(): void
    {
        // Refused rather than quietly accepted: an id nothing can resolve is
        // not a value worth storing.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/lines', ['code' => 'L-9', 'name' => 'Line 9', 'division_id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('division_id');
    }

    public function test_a_line_without_a_division_is_created_as_before(): void
    {
        // The other half: the guard must not cost anything to the ordinary case.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/lines', ['code' => 'L-8', 'name' => 'Line 8'])
            ->assertSuccessful();

        $this->assertDatabaseHas('lines', ['code' => 'L-8']);
    }

    public function test_an_explicit_null_division_is_still_accepted(): void
    {
        // `prohibited` refuses a value, not the absence of one — worth pinning,
        // because a client that sends every field as null is a common shape.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/lines', ['code' => 'L-7', 'name' => 'Line 7', 'division_id' => null])
            ->assertSuccessful();
    }

    public function test_a_required_skill_cannot_be_named_without_the_workforce_module(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/process-segments', [
            'code' => 'SEG-1',
            'name' => 'Cutting',
            'segment_type' => 'process',
            'required_skill_ids' => [1],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('required_skill_ids.0');
    }

    public function test_the_endpoints_are_closed_to_guests(): void
    {
        $this->getJson('/api/v1/employee-activities/team-day')->assertUnauthorized();
        $this->getJson('/api/v1/reports/production-cost')->assertUnauthorized();
    }
}
