<?php

namespace Tests\Feature\Api;

use App\Models\Crew;
use App\Models\CrewBreakWindow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewBreakWindowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Crew $crew;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->crew = Crew::factory()->create();
    }

    public function test_admin_can_list_break_windows(): void
    {
        CrewBreakWindow::factory()->create(['crew_id' => $this->crew->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/crew-break-windows')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'crew_id', 'crew_name', 'name', 'start_time', 'end_time', 'days_of_week', 'is_active'],
                ],
            ]);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/crew-break-windows')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/crew-break-windows')
            ->assertStatus(403);
    }

    public function test_admin_can_create_break_window(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/crew-break-windows', [
                'crew_id' => $this->crew->id,
                'name' => 'Lunch',
                'start_time' => '12:00',
                'end_time' => '12:30',
                'days_of_week' => [1, 2, 3, 4, 5],
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('crew_break_windows', [
            'crew_id' => $this->crew->id,
            'name' => 'Lunch',
        ]);
    }

    public function test_admin_can_delete_break_window(): void
    {
        $window = CrewBreakWindow::factory()->create(['crew_id' => $this->crew->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/crew-break-windows/{$window->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('crew_break_windows', ['id' => $window->id]);
    }
}
