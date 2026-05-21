<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Weekly Lathe Lubrication',
            'event_type'     => 'planned',
            'frequency'      => 'weekly',
            'interval_value' => 1,
            'lead_time_days' => 0,
            'next_due_at'    => '2026-06-01 08:00:00',
            'line_id'        => Line::factory()->create()->id,
            'is_active'      => '1',
        ], $overrides);
    }

    public function test_admin_can_list_schedules(): void
    {
        $line = Line::factory()->create();
        MaintenanceSchedule::factory()->create(['name' => 'Quarterly Press Check', 'line_id' => $line->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.maintenance-schedules.index'));

        $response->assertStatus(200);
        $response->assertSee('Quarterly Press Check');
    }

    public function test_guest_cannot_access_schedules(): void
    {
        $response = $this->get(route('admin.maintenance-schedules.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_create_schedule(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-schedules.store'), $this->validPayload());

        $response->assertRedirect(route('admin.maintenance-schedules.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_schedules', [
            'name'      => 'Weekly Lathe Lubrication',
            'frequency' => 'weekly',
            'is_active' => true,
        ]);
    }

    public function test_store_requires_one_of_tool_line_workstation(): void
    {
        $payload = $this->validPayload();
        unset($payload['line_id']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-schedules.store'), $payload);

        $response->assertSessionHasErrors(['tool_id', 'line_id', 'workstation_id']);
    }

    public function test_store_rejects_unknown_frequency(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-schedules.store'), $this->validPayload([
                'frequency' => 'biweekly',
            ]));

        $response->assertSessionHasErrors('frequency');
    }

    public function test_store_rejects_interval_below_one(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-schedules.store'), $this->validPayload([
                'interval_value' => 0,
            ]));

        $response->assertSessionHasErrors('interval_value');
    }

    public function test_admin_can_update_schedule(): void
    {
        $line = Line::factory()->create();
        $schedule = MaintenanceSchedule::factory()->create(['line_id' => $line->id, 'name' => 'Old Name']);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.maintenance-schedules.update', $schedule), $this->validPayload([
                'name'    => 'New Name',
                'line_id' => $line->id,
            ]));

        $response->assertRedirect(route('admin.maintenance-schedules.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_schedules', [
            'id'   => $schedule->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_destroy_schedule(): void
    {
        $line = Line::factory()->create();
        $schedule = MaintenanceSchedule::factory()->create(['line_id' => $line->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.maintenance-schedules.destroy', $schedule));

        $response->assertRedirect(route('admin.maintenance-schedules.index'));
        $this->assertDatabaseMissing('maintenance_schedules', ['id' => $schedule->id]);
    }

    public function test_generate_now_creates_event_for_active_schedule(): void
    {
        $line = Line::factory()->create();
        $schedule = MaintenanceSchedule::factory()->create([
            'line_id'     => $line->id,
            'next_due_at' => now()->addDays(20), // far future — would be skipped by hourly job
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-schedules.generate-now', $schedule));

        $response->assertRedirect(route('admin.maintenance-schedules.index'));
        $this->assertDatabaseHas('maintenance_events', [
            'schedule_id' => $schedule->id,
            'title'       => $schedule->name,
        ]);
    }

    public function test_generate_now_refuses_inactive_schedule(): void
    {
        $line = Line::factory()->create();
        $schedule = MaintenanceSchedule::factory()->inactive()->create(['line_id' => $line->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.maintenance-schedules.generate-now', $schedule));

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('maintenance_events', 0);
    }

    public function test_guest_cannot_create_schedule(): void
    {
        $response = $this->post(route('admin.maintenance-schedules.store'), $this->validPayload());

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('maintenance_schedules', ['name' => 'Weekly Lathe Lubrication']);
    }
}
