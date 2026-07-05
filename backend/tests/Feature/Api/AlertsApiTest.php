<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_load_alerts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/alerts')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['blocking_issues', 'non_blocking_issues', 'overdue_orders', 'blocked_orders', 'total'],
            ]);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/alerts')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/alerts')
            ->assertStatus(403);
    }
}
