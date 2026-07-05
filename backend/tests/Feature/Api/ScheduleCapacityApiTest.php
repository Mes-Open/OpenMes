<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleCapacityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function tokenFor(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->createToken('test')->plainTextToken;
    }

    private function auth(string $role)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($role));
    }

    public function test_supervisor_can_read_line_capacity_grid(): void
    {
        Line::factory()->create();

        $r = $this->auth('Supervisor')->getJson('/api/v1/schedule/capacity?axis=line&granularity=week');

        $r->assertStatus(200)
            ->assertJsonStructure([
                'grid' => ['granularity', 'buckets', 'resources'],
                'granularity', 'axis', 'range_start', 'range_end', 'nav_prev', 'nav_next',
            ])
            ->assertJsonPath('axis', 'line')
            ->assertJsonPath('granularity', 'week');
    }

    public function test_admin_can_read_crew_capacity_grid_daily(): void
    {
        $r = $this->auth('Admin')->getJson('/api/v1/schedule/capacity?axis=crew&granularity=day');

        $r->assertStatus(200)
            ->assertJsonPath('axis', 'crew')
            ->assertJsonPath('granularity', 'day');
    }

    public function test_invalid_axis_and_granularity_fall_back_to_defaults(): void
    {
        $r = $this->auth('Admin')->getJson('/api/v1/schedule/capacity?axis=bogus&granularity=bogus');

        $r->assertStatus(200)
            ->assertJsonPath('axis', 'line')
            ->assertJsonPath('granularity', 'week');
    }

    public function test_operator_cannot_read_capacity_grid(): void
    {
        $this->auth('Operator')->getJson('/api/v1/schedule/capacity')->assertStatus(403);
    }

    public function test_guest_cannot_read_capacity_grid(): void
    {
        $this->getJson('/api/v1/schedule/capacity')->assertStatus(401);
    }

    public function test_cell_drilldown_validates_input(): void
    {
        $this->auth('Admin')->getJson('/api/v1/schedule/capacity/cell')->assertStatus(422);
    }

    public function test_cell_drilldown_returns_orders_for_a_line(): void
    {
        $line = Line::factory()->create();

        $r = $this->auth('Supervisor')->getJson(
            '/api/v1/schedule/capacity/cell?line_id='.$line->id.'&start=2026-06-22&end=2026-06-28'
        );

        $r->assertStatus(200)->assertJsonStructure(['orders']);
    }
}
