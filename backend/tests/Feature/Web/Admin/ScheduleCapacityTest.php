<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Crew;
use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the schedule capacity view:
 *
 * - GET /admin/schedule/capacity — render + role guard + payload shape
 * - granularity toggle (week / day)
 *
 * Authorization mirrors the rest of the schedule tab: guests are redirected to
 * login and non-privileged roles get HTTP 403 via the tab-access middleware.
 */
class ScheduleCapacityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/schedule/capacity')->assertRedirect('/login');
    }

    public function test_non_privileged_role_is_forbidden(): void
    {
        $this->actingAs($this->operator)
            ->get('/admin/schedule/capacity')
            ->assertStatus(403);
    }

    public function test_admin_sees_capacity_grid_with_load(): void
    {
        $line = Line::factory()->create(['is_active' => true, 'name' => 'Cap Line']);
        Shift::create([
            'name' => 'Day',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00', // 8h × 5 weekdays = 40h
            'days_of_week' => [1, 2, 3, 4, 5],
            'line_id' => $line->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $monday = Carbon::now()->startOfWeek();
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => $monday->copy()->setTimeFromTimeString('06:00:00'),
            'planned_end_at' => $monday->copy()->setTimeFromTimeString('14:00:00'), // 8h
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule/capacity?granularity=week&start_date='.$monday->format('Y-m-d'));

        $response->assertOk();
        $response->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('admin/schedule/Capacity')
                ->where('granularity', 'week')
                ->where('axis', 'line')
                ->has('grid.buckets')
                ->has('grid.resources')
        );

        $grid = $response->viewData('page')['props']['grid'];
        $resource = collect($grid['resources'])->firstWhere('id', $line->id);
        $this->assertNotNull($resource);

        $firstBucket = $grid['buckets'][0]['key'];
        $cell = $resource['cells'][$firstBucket];
        $this->assertSame(40.0, $cell['available_h']);
        $this->assertSame(8.0, $cell['planned_h']);
        $this->assertSame(20, $cell['load_pct']);
    }

    public function test_daily_granularity_is_respected(): void
    {
        Line::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule/capacity?granularity=day');

        $response->assertOk();
        $response->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('granularity', 'day')
        );
    }

    public function test_invalid_granularity_falls_back_to_week(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule/capacity?granularity=bogus');

        $response->assertOk();
        $this->assertSame('week', $response->viewData('page')['props']['granularity']);
    }

    public function test_crew_axis_renders(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule/capacity?axis=crew');

        $response->assertOk();
        $response->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('admin/schedule/Capacity')
                ->where('axis', 'crew')
                ->has('grid.resources')
        );
    }

    public function test_cell_drilldown_returns_orders(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $monday = Carbon::now()->startOfWeek();
        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => $monday->copy()->addDay(),
            'process_snapshot' => ['steps' => [['estimated_duration_minutes' => 90]]],
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            '/admin/schedule/capacity/cell?'.http_build_query([
                'line_id' => $line->id,
                'start' => $monday->toDateString(),
                'end' => $monday->copy()->endOfWeek()->toDateString(),
            ])
        );

        $response->assertOk();
        $response->assertJsonPath('orders.0.id', $wo->id);
        $response->assertJsonPath('orders.0.hours', 1.5);
    }

    public function test_reschedule_payload_preserves_week_number_of_a_week_planned_order(): void
    {
        // A week-only order (no due_date) lives in the grid via week_number.
        // The drill-down reschedule echoes week_number/shift_number back so the
        // shared updateOrder endpoint cannot null them and orphan the order.
        $line = Line::factory()->create(['is_active' => true]);
        $otherLine = Line::factory()->create(['is_active' => true]);
        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => null,
            'week_number' => 30,
            'shift_number' => 2,
            'planned_start_at' => null,
            'planned_end_at' => null,
        ]);

        // What Capacity.jsx now sends when only the line is changed.
        $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'line_id' => $otherLine->id,
            'due_date' => null,
            'week_number' => 30,
            'shift_number' => 2,
        ])->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'line_id' => $otherLine->id,
            'week_number' => 30, // preserved — not nulled
            'shift_number' => 2,
        ]);
    }

    public function test_cell_drilldown_validates_input(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/admin/schedule/capacity/cell?line_id=999999&start=2026-06-01&end=2026-06-07')
            ->assertStatus(422);
    }

    public function test_cell_drilldown_forbidden_for_non_privileged(): void
    {
        $line = Line::factory()->create(['is_active' => true]);

        $this->actingAs($this->operator)
            ->getJson('/admin/schedule/capacity/cell?'.http_build_query([
                'line_id' => $line->id,
                'start' => now()->startOfWeek()->toDateString(),
                'end' => now()->endOfWeek()->toDateString(),
            ]))
            ->assertStatus(403);
    }

    public function test_cell_drilldown_redirects_guest_to_login(): void
    {
        $line = Line::factory()->create(['is_active' => true]);

        $this->get('/admin/schedule/capacity/cell?'.http_build_query([
            'line_id' => $line->id,
            'start' => now()->startOfWeek()->toDateString(),
            'end' => now()->endOfWeek()->toDateString(),
        ]))->assertRedirect('/login');
    }

    public function test_crew_axis_reports_available_hours_with_data(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        Shift::create([
            'name' => 'Day',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00', // 8h × 5 weekdays = 40h
            'days_of_week' => [1, 2, 3, 4, 5],
            'line_id' => $line->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $ws = Workstation::factory()->create(['line_id' => $line->id, 'is_active' => true]);
        $crew = Crew::factory()->create(['is_active' => true]);
        Worker::factory()->create(['crew_id' => $crew->id, 'workstation_id' => $ws->id, 'is_active' => true]);

        $monday = Carbon::now()->startOfWeek();
        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule/capacity?axis=crew&granularity=week&start_date='.$monday->format('Y-m-d'));

        $response->assertOk();
        $grid = $response->viewData('page')['props']['grid'];
        $resource = collect($grid['resources'])->firstWhere('id', $crew->id);
        $this->assertNotNull($resource);
        $this->assertSame(40.0, $resource['cells'][$grid['buckets'][0]['key']]['available_h']);
    }
}
