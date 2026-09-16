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

    public function test_daily_shows_one_day_and_keeps_time_based_order_after_a_move(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $day = '2026-09-16';
        $first = WorkOrder::factory()->create([
            'line_id' => $line->id, 'status' => WorkOrder::STATUS_PENDING,
            'due_date' => $day, 'planned_start_at' => "$day 08:00:00",
            'planned_end_at' => "$day 09:00:00",
        ]);
        $second = WorkOrder::factory()->create([
            'line_id' => $line->id, 'status' => WorkOrder::STATUS_PENDING,
            'due_date' => $day, 'planned_start_at' => "$day 10:00:00",
            'planned_end_at' => "$day 11:00:00",
        ]);
        $tomorrow = WorkOrder::factory()->create([
            'line_id' => $line->id, 'status' => WorkOrder::STATUS_PENDING,
            'due_date' => '2026-09-17', 'planned_start_at' => '2026-09-17 08:00:00',
            'planned_end_at' => '2026-09-17 09:00:00',
        ]);
        $query = ['view_mode' => 'daily', 'start_date' => $day];
        $props = $this->props($query);
        $this->assertSame($day, $props['rangeStart']);
        $this->assertSame($day, $props['rangeEnd']);
        $this->assertSame('2026-09-15', $props['navPrev']);
        $this->assertSame('2026-09-17', $props['navNext']);
        $this->assertNotContains($tomorrow->id, array_column($props['workOrders'], 'id'));

        // Drag the first block past the second without changing its duration.
        $this->actingAs($this->admin)->putJson("/admin/schedule/{$first->id}/resize", [
            'planned_start_at' => "$day 12:00:00",
            'planned_end_at' => "$day 13:00:00",
        ])->assertOk()->assertJson(['success' => true]);

        foreach (['daily', 'hourly'] as $mode) {
            $orders = collect($this->props([...$query, 'view_mode' => $mode])['workOrders'])
                ->sortBy('planned_start_at')->pluck('id')->values()->all();
            $this->assertSame([$second->id, $first->id], $orders);
        }
        $this->assertSame('10:00', $second->fresh()->planned_start_at->format('H:i'));
        $this->assertSame($day, $first->fresh()->due_date->format('Y-m-d'));
    }

    public function test_daily_includes_the_second_day_of_shift_based_ranges(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $other = Line::factory()->create(['is_active' => true]);
        $primary = WorkOrder::factory()->create([
            'line_id' => $line->id, 'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => '2026-09-16 06:00:00', 'planned_end_at' => null,
            'due_date' => '2026-09-30', 'end_date' => '2026-09-17', 'end_shift_number' => 1,
        ]);
        $legacy = WorkOrder::factory()->create([
            'line_id' => $line->id, 'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => null, 'planned_end_at' => null,
            'due_date' => '2026-09-16', 'end_date' => '2026-09-17',
        ]);
        $extra = WorkOrder::factory()->create([
            'line_id' => $other->id, 'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => '2026-09-01 06:00:00', 'planned_end_at' => null,
            'due_date' => '2026-09-01',
        ]);
        $extra->extraPlacements()->create([
            'line_id' => $line->id, 'due_date' => '2026-09-16',
            'end_date' => '2026-09-17', 'shift_number' => 1, 'end_shift_number' => 1,
        ]);
        $props = $this->props(['view_mode' => 'daily', 'start_date' => '2026-09-17', 'line_id' => $line->id]);
        $ids = array_column($props['workOrders'], 'id');
        // The payload also retains the final shift's possible overnight tail.
        $tail = $this->props(['view_mode' => 'daily', 'start_date' => '2026-09-18', 'line_id' => $line->id]);
        $this->assertContains($extra->id, array_column($tail['workOrders'], 'id'));
        foreach ([$primary, $legacy, $extra] as $order) {
            $this->assertContains($order->id, $ids);
        }
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
