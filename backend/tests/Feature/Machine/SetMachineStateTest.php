<?php

namespace Tests\Feature\Machine;

use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manual machine-state setting endpoints (#87): operators set their line's
 * workstation states from the operator panel; supervisors/admins from the
 * machine monitor.
 */
class SetMachineStateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Line $line;

    private Workstation $workstation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->line = Line::factory()->create();
        $this->workstation = Workstation::factory()->create(['line_id' => $this->line->id]);
    }

    public function test_admin_can_set_state_from_the_monitor(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/machine-monitor/{$this->workstation->id}/state", ['state' => 'MAINTENANCE'])
            ->assertOk();

        $this->assertDatabaseHas('workstation_states', [
            'workstation_id' => $this->workstation->id,
            'state' => 'MAINTENANCE',
            'source' => 'manual',
            'ended_at' => null,
        ]);
    }

    public function test_monitor_rejects_an_invalid_state(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/machine-monitor/{$this->workstation->id}/state", ['state' => 'TELEPORTING'])
            ->assertStatus(422)->assertJsonValidationErrors('state');
    }

    public function test_guest_cannot_set_state_from_the_monitor(): void
    {
        $this->post("/admin/machine-monitor/{$this->workstation->id}/state", ['state' => 'WAITING'])
            ->assertRedirect('/login');

        $this->assertDatabaseMissing('workstation_states', ['workstation_id' => $this->workstation->id]);
    }

    public function test_operator_cannot_set_state_from_the_monitor(): void
    {
        $this->actingAs($this->operator)
            ->post("/admin/machine-monitor/{$this->workstation->id}/state", ['state' => 'WAITING'])
            ->assertForbidden();

        $this->assertDatabaseMissing('workstation_states', ['workstation_id' => $this->workstation->id]);
    }

    public function test_operator_can_set_state_for_a_workstation_on_their_line(): void
    {
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post("/operator/workstation/machine-state/{$this->workstation->id}", ['state' => 'CLEANING'])
            ->assertRedirect();

        $this->assertDatabaseHas('workstation_states', [
            'workstation_id' => $this->workstation->id,
            'state' => 'CLEANING',
            'source' => 'manual',
        ]);
    }

    public function test_operator_cannot_set_state_for_a_workstation_on_another_line(): void
    {
        $otherLine = Line::factory()->create();
        $otherWs = Workstation::factory()->create(['line_id' => $otherLine->id]);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post("/operator/workstation/machine-state/{$otherWs->id}", ['state' => 'WAITING'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('workstation_states', ['workstation_id' => $otherWs->id]);
    }

    public function test_guest_cannot_set_state(): void
    {
        $this->post("/operator/workstation/machine-state/{$this->workstation->id}", ['state' => 'WAITING'])
            ->assertRedirect('/login');

        $this->assertDatabaseMissing('workstation_states', ['workstation_id' => $this->workstation->id]);
    }
}
