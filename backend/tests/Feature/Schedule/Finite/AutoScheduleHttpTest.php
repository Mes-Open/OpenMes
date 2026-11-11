<?php

namespace Tests\Feature\Schedule\Finite;

use App\Models\ScheduledOperation;
use App\Models\ScheduleSimulation;
use App\Models\User;
use App\Models\WorkstationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Planner page and API for finite-capacity scheduling: happy paths,
 * validation, authorization (guest, operator, supervisor without the tab),
 * stale drafts, overrides, and manual moves invalidating committed operations.
 */
class AutoScheduleHttpTest extends TestCase
{
    use RefreshDatabase;
    use SchedulesFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchedule();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function user(string $role, bool $scheduleTab = false): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        if ($scheduleTab) {
            $user->givePermissionTo(Permission::findOrCreate('tab:schedule', 'web'));
        }

        return $user;
    }

    // ── web ─────────────────────────────────────────────────────────────────

    public function test_admin_sees_the_page_with_the_current_draft(): void
    {
        $this->order($this->line(), [['estimated_duration_minutes' => 60]]);
        $admin = $this->user('Admin');

        $this->actingAs($admin)->post('/admin/schedule/simulations', ['horizon_days' => 7])
            ->assertRedirect('/admin/schedule/auto')
            ->assertSessionHas('success');

        $this->actingAs($admin)->get('/admin/schedule/auto')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/schedule/AutoSchedule')
                ->where('draft.status', 'draft')
                ->has('draft.operations', 1)
                ->where('draft.operations.0.status', 'scheduled')
                ->where('canDecide', true));
    }

    public function test_the_draft_shows_people_shortfalls_and_untracked_lines(): void
    {
        $lineA = $this->line(['name' => 'Line A']);
        $lineB = $this->line(['name' => 'Line B']);
        foreach ([1, 2] as $i) {
            $this->user('Operator')->lines()->attach($lineA->id);
        }
        $this->order($lineA, [['estimated_duration_minutes' => 60, 'required_operators' => 3]], ['priority' => 2]);
        $this->order($lineB, [['estimated_duration_minutes' => 60, 'required_operators' => 3]], ['priority' => 1]);
        $admin = $this->user('Admin');
        $this->get('/admin/schedule/auto')->assertRedirect('/login');

        $this->actingAs($admin)->post('/admin/schedule/simulations', ['horizon_days' => 7])->assertRedirect('/admin/schedule/auto');

        $this->actingAs($admin)->get('/admin/schedule/auto')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/schedule/AutoSchedule')
                ->where('draft.workforce.0.line_id', $lineA->id)
                ->where('draft.workforce.0.tracked', true)
                ->where('draft.workforce.0.assigned', 2)
                ->where('draft.workforce.1.line_id', $lineB->id)
                ->where('draft.workforce.1.tracked', false)
                ->where('draft.workforce.1.assigned', null)
                ->where('draft.operations.0.status', 'infeasible_workforce_capacity')
                ->where('draft.operations.0.reasons.0.code', 'workforce_capacity')
                ->where('draft.operations.0.reasons.0.required', 3)
                ->where('draft.operations.0.reasons.0.available', 2)
                ->where('draft.operations.0.reasons.0.message', 'Needs 3 operators, 2 available.')
                ->where('draft.operations.1.status', 'scheduled'));

        // The staffing breakdown is no wider exposed than the page itself.
        $this->actingAs($this->user('Operator'))->get('/admin/schedule/auto')->assertForbidden();

        $id = ScheduleSimulation::sole()->id;
        $this->actingAs($admin, 'sanctum')->getJson("/api/v1/schedule/simulations/{$id}")
            ->assertOk()
            ->assertJsonPath('data.workforce.1.tracked', false)
            ->assertJsonPath('data.operations.0.reasons.0.required', 3);
    }

    public function test_supervisor_with_the_schedule_tab_can_simulate_and_apply(): void
    {
        $order = $this->order($this->line(), [['estimated_duration_minutes' => 60]]);
        $supervisor = $this->user('Supervisor', scheduleTab: true);

        $this->actingAs($supervisor)->post('/admin/schedule/simulations')->assertRedirect();
        $simulation = ScheduleSimulation::sole();

        $this->actingAs($supervisor)->post("/admin/schedule/simulations/{$simulation->id}/apply")
            ->assertRedirect('/admin/schedule/auto')
            ->assertSessionHas('success');

        $this->assertNotNull($order->fresh()->planned_start_at);
        $this->assertSame(ScheduleSimulation::STATUS_APPLIED, $simulation->fresh()->status);
    }

    public function test_discard_via_web(): void
    {
        $this->order($this->line(), [['estimated_duration_minutes' => 60]]);
        $admin = $this->user('Admin');
        $this->actingAs($admin)->post('/admin/schedule/simulations');
        $simulation = ScheduleSimulation::sole();

        $this->actingAs($admin)->post("/admin/schedule/simulations/{$simulation->id}/discard")
            ->assertRedirect('/admin/schedule/auto')
            ->assertSessionHas('success');

        $this->assertSame(ScheduleSimulation::STATUS_DISCARDED, $simulation->fresh()->status);
    }

    public function test_applying_a_stale_draft_flashes_an_error(): void
    {
        $order = $this->order($this->line(), [['estimated_duration_minutes' => 60]]);
        $admin = $this->user('Admin');
        $this->actingAs($admin)->post('/admin/schedule/simulations');
        $simulation = ScheduleSimulation::sole();
        $this->travel(1)->minutes();
        $order->update(['priority' => 5]);

        $this->actingAs($admin)->post("/admin/schedule/simulations/{$simulation->id}/apply")
            ->assertRedirect('/admin/schedule/auto')
            ->assertSessionHas('error');

        $this->assertNull($order->fresh()->planned_start_at);
        $this->assertTrue($simulation->fresh()->isDraft());
    }

    public function test_generate_validates_its_input(): void
    {
        $admin = $this->user('Admin');

        $this->actingAs($admin)
            ->post('/admin/schedule/simulations', ['horizon_days' => 0, 'scope' => 'everything', 'start' => 'not-a-date', 'work_order_ids' => [999999]])
            ->assertSessionHasErrors(['horizon_days', 'scope', 'start', 'work_order_ids.0']);
        $this->actingAs($admin)
            ->post('/admin/schedule/simulations', ['horizon_days' => 91])
            ->assertSessionHasErrors(['horizon_days']);

        $this->assertSame(0, ScheduleSimulation::count());
    }

    public function test_guest_operator_and_supervisor_without_tab_are_refused(): void
    {
        $this->order($this->line(), [['estimated_duration_minutes' => 60]]);

        $this->get('/admin/schedule/auto')->assertRedirect('/login');
        $this->post('/admin/schedule/simulations')->assertRedirect('/login');

        $operator = $this->user('Operator');
        $this->actingAs($operator)->get('/admin/schedule/auto')->assertForbidden();
        $this->actingAs($operator)->post('/admin/schedule/simulations')->assertForbidden();

        $this->actingAs($this->user('Supervisor'))->post('/admin/schedule/simulations')->assertForbidden();

        $this->assertSame(0, ScheduleSimulation::count());
    }

    public function test_a_role_with_the_tab_but_not_supervisor_cannot_decide(): void
    {
        $this->order($this->line(), [['estimated_duration_minutes' => 60]]);
        $operator = $this->user('Operator', scheduleTab: true);

        $this->actingAs($operator)->get('/admin/schedule/auto')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canDecide', false));
        $this->actingAs($operator)->post('/admin/schedule/simulations')->assertForbidden();
    }

    public function test_override_via_web_respects_the_equipment_class(): void
    {
        $line = $this->line();
        $type = WorkstationType::factory()->create();
        $this->station($line, $type);
        $other = $this->station($line, $type);
        $foreign = $this->station($line, WorkstationType::factory()->create());
        $this->order($line, [['estimated_duration_minutes' => 60, 'workstation_type_id' => $type->id]]);
        $admin = $this->user('Admin');
        $this->actingAs($admin)->post('/admin/schedule/simulations');
        $op = ScheduledOperation::sole();

        $this->actingAs($admin)->put("/admin/schedule/operations/{$op->id}", ['workstation_id' => $foreign->id])
            ->assertSessionHasErrors('workstation_id');
        $this->actingAs($admin)->put("/admin/schedule/operations/{$op->id}", [])
            ->assertSessionHasErrors('workstation_id');
        $this->actingAs($admin)->put("/admin/schedule/operations/{$op->id}", ['workstation_id' => $other->id])
            ->assertRedirect('/admin/schedule/auto');

        $this->assertSame($other->id, $op->fresh()->workstation_id);
    }

    public function test_moving_an_order_by_hand_drops_its_committed_operations(): void
    {
        $order = $this->order($this->line(), [['estimated_duration_minutes' => 60]]);
        $admin = $this->user('Admin');
        $this->actingAs($admin)->post('/admin/schedule/simulations');
        $this->actingAs($admin)->post('/admin/schedule/simulations/'.ScheduleSimulation::sole()->id.'/apply');
        $this->assertSame(1, ScheduledOperation::committed()->count());

        $this->actingAs($admin)->putJson("/admin/schedule/{$order->id}/resize", [
            'planned_start_at' => '2026-10-13 06:00:00',
            'planned_end_at' => '2026-10-13 07:00:00',
        ])->assertOk();

        $this->assertSame(0, ScheduledOperation::committed()->count());
    }

    // ── API ─────────────────────────────────────────────────────────────────

    public function test_api_generate_show_apply_and_read_the_committed_plan(): void
    {
        $line = $this->line();
        $order = $this->order($line, [['estimated_duration_minutes' => 60], ['estimated_duration_minutes' => 30]]);
        $supervisor = $this->user('Supervisor');

        $id = $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/v1/schedule/simulations', ['horizon_days' => 7])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.operations.0.status', 'scheduled')
            ->assertJsonPath('data.counts.scheduled', 2)
            ->json('data.id');

        $this->actingAs($supervisor, 'sanctum')->getJson("/api/v1/schedule/simulations/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->actingAs($supervisor, 'sanctum')->postJson("/api/v1/schedule/simulations/{$id}/apply")
            ->assertOk()
            ->assertJsonPath('applied', 1);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/v1/schedule/operations?work_order_id={$order->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.simulation_id', null)
            ->assertJsonPath('data.0.planned_start_at', $this->at('2026-10-12 06:00')->toIso8601String())
            ->assertJsonStructure(['data' => [['id', 'work_order_id', 'step_number', 'workstation_id', 'line_id', 'planned_start_at', 'planned_end_at', 'status', 'reasons']]]);

        // Applying twice is refused.
        $this->actingAs($supervisor, 'sanctum')->postJson("/api/v1/schedule/simulations/{$id}/apply")->assertStatus(409);
    }

    public function test_api_exposes_machine_readable_infeasibility(): void
    {
        $type = WorkstationType::factory()->create();
        $this->order($this->line(), [['estimated_duration_minutes' => 60, 'workstation_type_id' => $type->id]]);

        $this->actingAs($this->user('Admin'), 'sanctum')
            ->postJson('/api/v1/schedule/simulations')
            ->assertCreated()
            ->assertJsonPath('data.operations.0.status', 'infeasible_no_eligible_workstation')
            ->assertJsonPath('data.operations.0.reasons.0.code', 'no_eligible_workstation')
            ->assertJsonPath('data.operations.0.planned_start_at', null);
    }

    public function test_api_validation_and_authorization(): void
    {
        $this->getJson('/api/v1/schedule/operations')->assertUnauthorized();
        $this->postJson('/api/v1/schedule/simulations')->assertUnauthorized();

        $operator = $this->user('Operator');
        $this->actingAs($operator, 'sanctum')->getJson('/api/v1/schedule/operations')->assertForbidden();
        $this->actingAs($operator, 'sanctum')->postJson('/api/v1/schedule/simulations')->assertForbidden();

        $admin = $this->user('Admin');
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/schedule/simulations', ['horizon_days' => 500])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('horizon_days');
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/schedule/operations?status[]=bogus')
            ->assertUnprocessable();
    }
}
