<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Board-level payload coverage for the rebuilt schedule planner (the client
 * computes layout from the controller's flat work-order + shift lists):
 *
 * - weekly ships exactly the rendered week's orders (no later-week orphans)
 * - distinct shift slots that share a sort_order are not collapsed into one
 */
class SchedulePlannerBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin)->get('/admin/schedule?'.http_build_query($query));
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_weekly_ships_only_the_rendered_week(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $monday = Carbon::now()->startOfWeek();

        $thisWeek = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => $monday->copy()->addDay(),       // Tuesday this week
        ]);
        $nextWeek = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => $monday->copy()->addWeek()->addDay(), // Tuesday next week
        ]);

        $ids = collect($this->props([
            'view_mode' => 'weekly',
            'start_date' => $monday->format('Y-m-d'),
        ])['workOrders'])->pluck('id');

        // Only this week's order is shipped; next week's has no column so it
        // must not be sent (it would render nowhere).
        $this->assertContains($thisWeek->id, $ids->all());
        $this->assertNotContains($nextWeek->id, $ids->all());
    }

    public function test_distinct_shifts_sharing_a_sort_order_are_not_collapsed(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        // Two genuinely different shift windows that share a sort_order — using
        // unusual times so they can't coincide with any environment-seeded shift.
        Shift::create(['name' => 'Slot A', 'start_time' => '03:00:00', 'end_time' => '04:00:00', 'days_of_week' => [1, 2, 3, 4, 5], 'line_id' => $line->id, 'is_active' => true, 'sort_order' => 9]);
        Shift::create(['name' => 'Slot B', 'start_time' => '04:00:00', 'end_time' => '05:00:00', 'days_of_week' => [1, 2, 3, 4, 5], 'line_id' => $line->id, 'is_active' => true, 'sort_order' => 9]);

        $props = $this->props(['view_mode' => 'weekly']);

        // Deduped by time window, not sort_order, so both distinct same-sort_order
        // slots survive (dedup-by-sort_order would have dropped one).
        $windows = collect($props['shifts'])->map(fn ($s) => substr($s['start_time'], 0, 5).'-'.substr($s['end_time'], 0, 5))->all();
        $this->assertContains('03:00-04:00', $windows);
        $this->assertContains('04:00-05:00', $windows);
    }
}
