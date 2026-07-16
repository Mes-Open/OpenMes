<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_load_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'total_work_orders', 'pending', 'in_progress', 'blocked',
                        'active_today', 'open_issues', 'blocking_issues', 'active_lines',
                    ],
                    'oee',
                    'inbound_qc' => ['pending', 'completed_30d', 'failed_30d', 'conditional_30d', 'pass_rate_30d'],
                    'materials' => ['low_stock_count', 'expiring_count', 'reserved_total', 'lots_total', 'quarantined_count'],
                    'scrap' => ['total_qty_30d', 'entries_30d', 'top_reason', 'top_reason_qty'],
                    'non_conformance' => ['open_total', 'open_by_type', 'disposition_summary', 'overdue_actions'],
                    'recent_work_orders',
                    'open_issues',
                    'lines',
                ],
            ]);
    }

    public function test_supervisor_can_load_dashboard(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(200);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403);
    }
}
