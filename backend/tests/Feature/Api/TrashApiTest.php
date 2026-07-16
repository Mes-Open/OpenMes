<?php

namespace Tests\Feature\Api;

use App\Models\Crew;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_sees_soft_deleted_rows(): void
    {
        $crew = Crew::factory()->create();
        $crew->delete();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/trash')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['items', 'counts', 'selected_type']])
            ->assertJsonFragment(['type' => 'crews', 'id' => $crew->id]);
    }

    public function test_admin_can_restore(): void
    {
        $crew = Crew::factory()->create();
        $crew->delete();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/trash/crews/{$crew->id}/restore")
            ->assertStatus(200);

        $this->assertDatabaseHas('crews', ['id' => $crew->id, 'deleted_at' => null]);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/trash')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/trash')
            ->assertStatus(403);
    }
}
