<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->line = Line::factory()->create();
    }

    public function test_supervisor_or_admin_can_get_fleet_status(): void
    {
        Workstation::factory()->create(['line_id' => $this->line->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/machine-monitor')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['tiles', 'states']]);

        // States is the fixed enum set; the fleet read model may be empty for a
        // bare workstation, so we assert the envelope, not a specific tile.
        $this->assertNotEmpty($response->json('data.states'));
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/machine-monitor')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/machine-monitor')
            ->assertStatus(403);
    }

    public function test_admin_can_set_workstation_state(): void
    {
        $ws = Workstation::factory()->create(['line_id' => $this->line->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/machine-monitor/{$ws->id}/state", ['state' => WorkstationState::MAINTENANCE])
            ->assertStatus(200);

        $this->assertDatabaseHas('workstation_states', [
            'workstation_id' => $ws->id,
            'state' => WorkstationState::MAINTENANCE,
        ]);
    }
}
