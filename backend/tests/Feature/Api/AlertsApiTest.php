<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WorkOrder;
use App\Support\TimezoneRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlertsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        TimezoneRegistry::flush();
        date_default_timezone_set('UTC');
        config(['app.timezone' => 'UTC']);

        parent::tearDown();
    }

    public function test_overdue_orders_use_the_plant_date(): void
    {
        // 00:30 on 15 June in Warsaw, still 14 June in UTC.
        TimezoneRegistry::flush();
        TimezoneRegistry::save('Europe/Warsaw');
        TimezoneRegistry::apply();
        Carbon::setTestNow(Carbon::parse('2026-06-14 22:30:00 UTC'));

        $dueToday = WorkOrder::factory()->create(['due_date' => '2026-06-15 00:00:00', 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $dueYesterday = WorkOrder::factory()->create(['due_date' => '2026-06-14 00:00:00', 'status' => WorkOrder::STATUS_PENDING]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $ids = collect(
            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/alerts')
                ->assertOk()
                ->json('data.overdue_orders')
        )->pluck('id');

        $this->assertTrue($ids->contains($dueYesterday->id));
        $this->assertFalse($ids->contains($dueToday->id));
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
