<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Crew;
use App\Models\CrewBreakWindow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrewBreakWindowControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'crew_id' => Crew::factory()->create()->id,
            'name' => 'Lunch',
            'start_time' => '12:00',
            'end_time' => '12:30',
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ], $overrides);
    }

    public function test_admin_can_view_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.crew-break-windows.index'))
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/crew-break-windows/Index'));
    }

    public function test_operator_cannot_access(): void
    {
        $this->actingAs($this->operator)
            ->get(route('admin.crew-break-windows.index'))
            ->assertStatus(403);
    }

    public function test_guest_cannot_create(): void
    {
        $this->post(route('admin.crew-break-windows.store'), $this->payload())
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_create_break_window(): void
    {
        $crew = Crew::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.crew-break-windows.store'), $this->payload([
                'crew_id' => $crew->id,
                'name' => 'Morning break',
                'start_time' => '10:00',
                'end_time' => '10:15',
                'days_of_week' => [1, 3, 5],
            ]));

        $response->assertRedirect(route('admin.crew-break-windows.index'));
        $response->assertSessionHasNoErrors();

        $window = CrewBreakWindow::where('crew_id', $crew->id)->first();
        $this->assertNotNull($window);
        $this->assertSame('Morning break', $window->name);
        $this->assertSame([1, 3, 5], $window->days_of_week);
        $this->assertTrue($window->is_active);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.crew-break-windows.store'), $this->payload([
                'start_time' => '12:30',
                'end_time' => '12:00',
            ]))
            ->assertSessionHasErrors('end_time');
    }

    public function test_days_of_week_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.crew-break-windows.store'), $this->payload(['days_of_week' => []]))
            ->assertSessionHasErrors('days_of_week');
    }

    public function test_invalid_weekday_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.crew-break-windows.store'), $this->payload(['days_of_week' => [0, 8]]))
            ->assertSessionHasErrors('days_of_week.0');
    }

    public function test_overlapping_window_on_shared_day_is_rejected(): void
    {
        $crew = Crew::factory()->create();
        CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id,
            'start_time' => '12:00',
            'end_time' => '12:30',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.crew-break-windows.store'), $this->payload([
                'crew_id' => $crew->id,
                'start_time' => '12:15',
                'end_time' => '12:45',
                'days_of_week' => [5, 6], // shares Friday
            ]))
            ->assertSessionHasErrors('start_time');
    }

    public function test_non_overlapping_window_on_different_day_is_allowed(): void
    {
        $crew = Crew::factory()->create();
        CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id,
            'start_time' => '12:00',
            'end_time' => '12:30',
            'days_of_week' => [1, 2, 3], // Mon–Wed
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.crew-break-windows.store'), $this->payload([
                'crew_id' => $crew->id,
                'start_time' => '12:00',
                'end_time' => '12:30',
                'days_of_week' => [4, 5], // Thu–Fri, no shared day
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_can_update_break_window(): void
    {
        $window = CrewBreakWindow::factory()->create(['name' => 'Lunch']);

        $this->actingAs($this->admin)
            ->put(route('admin.crew-break-windows.update', $window), $this->payload([
                'crew_id' => $window->crew_id,
                'name' => 'Renamed break',
                'days_of_week' => [6, 7],
            ]))
            ->assertRedirect(route('admin.crew-break-windows.index'))
            ->assertSessionHasNoErrors();

        $window->refresh();
        $this->assertSame('Renamed break', $window->name);
        $this->assertSame([6, 7], $window->days_of_week);
    }

    public function test_update_does_not_clash_with_itself(): void
    {
        $window = CrewBreakWindow::factory()->create([
            'start_time' => '12:00',
            'end_time' => '12:30',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        // Saving the same row unchanged must not trip the overlap guard.
        $this->actingAs($this->admin)
            ->put(route('admin.crew-break-windows.update', $window), $this->payload([
                'crew_id' => $window->crew_id,
                'start_time' => '12:00',
                'end_time' => '12:30',
                'days_of_week' => [1, 2, 3, 4, 5],
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_can_delete_break_window(): void
    {
        $window = CrewBreakWindow::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.crew-break-windows.destroy', $window))
            ->assertRedirect(route('admin.crew-break-windows.index'));

        $this->assertSoftDeleted('crew_break_windows', ['id' => $window->id]);
    }
}
