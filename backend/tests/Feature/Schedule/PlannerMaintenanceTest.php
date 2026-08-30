<?php

namespace Tests\Feature\Schedule;

use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Placing maintenance onto the planner: a defined schedule pre-fills it, or an
 * ad-hoc title works. The event lands as a pending maintenance tile.
 */
class PlannerMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin'));
        $this->operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));
    }

    public function test_admin_can_place_a_defined_maintenance_from_the_planner(): void
    {
        $line = Line::factory()->create();
        $schedule = MaintenanceSchedule::factory()->create([
            'name' => 'Weekly lube',
            'event_type' => 'planned',
            'line_id' => $line->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.schedule.maintenance.store'), [
            'schedule_id' => $schedule->id,
            'line_id' => $line->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
            'duration_minutes' => 90,
        ])->assertRedirect();

        $this->assertDatabaseHas('maintenance_events', [
            'title' => 'Weekly lube',
            'event_type' => 'planned',
            'line_id' => $line->id,
            'schedule_id' => $schedule->id,
            'status' => MaintenanceEvent::STATUS_PENDING,
        ]);
    }

    public function test_admin_can_place_an_adhoc_maintenance(): void
    {
        $line = Line::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.schedule.maintenance.store'), [
            'title' => 'Belt swap',
            'event_type' => 'corrective',
            'line_id' => $line->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
            'duration_minutes' => 30,
        ])->assertRedirect();

        $event = MaintenanceEvent::firstWhere('title', 'Belt swap');
        $this->assertNotNull($event);
        $this->assertSame($line->id, $event->line_id);
        $this->assertSame(30, (int) $event->scheduled_at->diffInMinutes($event->scheduled_end_at));
    }

    public function test_line_and_a_maintenance_are_required(): void
    {
        $this->actingAs($this->admin)->post(route('admin.schedule.maintenance.store'), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors(['line_id', 'title']);
    }

    public function test_operator_cannot_place_maintenance(): void
    {
        $line = Line::factory()->create();

        $this->actingAs($this->operator)->post(route('admin.schedule.maintenance.store'), [
            'title' => 'X', 'event_type' => 'planned', 'line_id' => $line->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
        ])->assertForbidden();

        $this->assertDatabaseCount('maintenance_events', 0);
    }
}
