<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionCostReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_load_production_cost_report(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/reports/production-cost')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['orders', 'material_cost', 'labor_cost', 'additional_cost', 'total_cost', 'avg_cost_per_unit', 'currency'],
                    'orders',
                    'currency',
                ],
            ]);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/reports/production-cost')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/reports/production-cost')
            ->assertStatus(403);
    }
}
