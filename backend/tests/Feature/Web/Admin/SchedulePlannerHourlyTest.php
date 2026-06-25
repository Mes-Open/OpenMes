<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the hourly (minute-level) view of the schedule planner:
 *
 * - GET /admin/schedule?view_mode=hourly  — render + role guard + payload
 * - PUT /admin/schedule/{wo}              — minute-level update + conflict
 * - PUT /admin/schedule/{wo}/resize       — minute-level resize + conflict
 *
 * Conflict detection is per-line: overlapping minute windows on the same line
 * must yield HTTP 409 with `{success: false, conflict: true, ...}` unless the
 * caller passes `force_conflict=1`. Overlap across different lines is allowed.
 */
class SchedulePlannerHourlyTest extends TestCase
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

    // ── helpers ──────────────────────────────────────────────────────────────

    private function createLine(array $attrs = []): Line
    {
        return Line::factory()->create(array_merge(['is_active' => true], $attrs));
    }

    private function createWO(Line $line, array $attrs = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_qty' => 100,
        ], $attrs));
    }

    /**
     * Build a Carbon timestamp anchored to the Monday of the current ISO week
     * so the controller's default `startOfWeek()` window covers everything we
     * seed inside this method. The hourly view uses `startDate` (Monday) as
     * the day to render unless `start_date` is passed explicitly.
     */
    private function mondayAt(string $time): Carbon
    {
        return now()->startOfWeek()->setTimeFromTimeString($time);
    }

    // ── view rendering ───────────────────────────────────────────────────────

    public function test_admin_can_view_hourly_mode(): void
    {
        $line = $this->createLine(['name' => 'Hourly Test Line']);

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule?view_mode=hourly');

        $response->assertOk();

        // The planner is now a React/Inertia page; its data is exposed as
        // Inertia props (nested under the root Blade view's `page` variable)
        // rather than Blade view variables.
        $response->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('admin/schedule/Planner')
                ->where('viewMode', 'hourly')
                ->where('slotMinutes', 15)
                ->has('lines')
                ->has('workOrders')
        );

        // Default slot granularity is 15 min.
        $this->assertSame(15, $response->viewData('page')['props']['slotMinutes']);
    }

    public function test_non_admin_cannot_access(): void
    {
        $response = $this->actingAs($this->operator)
            ->get('/admin/schedule?view_mode=hourly');

        $response->assertStatus(403);
    }

    public function test_weekly_payload_is_flat(): void
    {
        $line = $this->createLine(['name' => 'Weekly Line', 'code' => 'WK-1']);
        Shift::create([
            'name' => 'Morning',
            'sort_order' => 1,
            'is_active' => true,
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        $monday = now()->startOfWeek();
        $scheduled = $this->createWO($line, [
            'order_no' => 'WO-WK-SCHED',
            'due_date' => $monday->copy()->addDay(),
            'shift_number' => 1,
        ]);
        // Unassigned (no line) → backlog rail.
        $backlog = WorkOrder::factory()->create([
            'order_no' => 'WO-WK-BACKLOG',
            'line_id' => null,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_qty' => 50,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule?start_date='.$monday->format('Y-m-d'));

        $response->assertOk();
        $response->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('admin/schedule/Planner')
                ->where('viewMode', 'weekly')
                ->has('workOrders')
                ->has('lines')
                ->has('shifts')
                ->has('backlogOrders')
        );

        $props = $response->viewData('page')['props'];

        // Shifts carry start/end time for the hourly timeline.
        $shift = collect($props['shifts'])->firstWhere('sort_order', 1);
        $this->assertNotNull($shift);
        $this->assertArrayHasKey('start_time', $shift);

        // The scheduled order is shipped flat with its placement fields.
        $row = collect($props['workOrders'])->firstWhere('id', $scheduled->id);
        $this->assertNotNull($row);
        $this->assertSame($line->id, $row['line_id']);
        $this->assertSame(1, $row['shift_number']);
        $this->assertNotNull($row['due_date']);

        // The unassigned order lands in the backlog payload.
        $this->assertNotNull(collect($props['backlogOrders'])->firstWhere('id', $backlog->id));
    }

    public function test_hourly_data_includes_wo_with_planned_times(): void
    {
        $line = $this->createLine();
        $start = $this->mondayAt('08:00');
        $wo = $this->createWO($line, [
            'order_no' => 'WO-HOUR-VIS',
            'planned_start_at' => $start,
            'planned_end_at' => $start->copy()->setTimeFromTimeString('11:00'),
        ]);

        $startDate = $start->copy()->startOfWeek()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/admin/schedule?view_mode=hourly&start_date={$startDate}");

        $response->assertOk();
        $response->assertSee('WO-HOUR-VIS');

        // The controller now ships a flat `workOrders` list; the React planner
        // computes the hourly lane layout client-side. Assert the minute-planned
        // order is present with its line + timestamps intact.
        $wos = $response->viewData('page')['props']['workOrders'];
        $row = collect($wos)->firstWhere('id', $wo->id);
        $this->assertNotNull($row);
        $this->assertSame($line->id, $row['line_id']);
        $this->assertNotNull($row['planned_start_at']);
        $this->assertNotNull($row['planned_end_at']);
    }

    public function test_hourly_data_excludes_wo_on_other_day(): void
    {
        $line = $this->createLine();
        $monday = now()->startOfWeek();
        // WO scheduled for the previous Saturday — outside the rendered day.
        // We give the WO a far-future due_date and a non-matching week_number
        // so it does not leak into the backlog rendered alongside the planner.
        $start = $monday->copy()->subDays(2)->setTimeFromTimeString('09:00');
        $this->createWO($line, [
            'order_no' => 'WO-OTHER-DAY',
            'due_date' => $monday->copy()->addMonths(6),
            'week_number' => $start->isoWeek(),
            'planned_start_at' => $start,
            'planned_end_at' => $start->copy()->setTimeFromTimeString('12:00'),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule?view_mode=hourly&start_date='.$monday->format('Y-m-d'));

        $response->assertOk();

        // A WO whose window sits entirely on another day must not be shipped in
        // the visible-range `workOrders` payload for the rendered day.
        $wos = $response->viewData('page')['props']['workOrders'];
        $this->assertNull(collect($wos)->firstWhere('order_no', 'WO-OTHER-DAY'));
    }

    // ── updateOrder() minute-level ───────────────────────────────────────────

    public function test_update_order_accepts_planned_timestamps(): void
    {
        $line = $this->createLine();
        $wo = $this->createWO($line);

        $start = $this->mondayAt('07:30');
        $end = $this->mondayAt('09:45');

        $response = $this->actingAs($this->admin)->putJson(
            "/admin/schedule/{$wo->id}",
            [
                'line_id' => $line->id,
                'planned_start_at' => $start->toDateTimeString(),
                'planned_end_at' => $end->toDateTimeString(),
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'planned_start_at' => $start->toDateTimeString(),
            'planned_end_at' => $end->toDateTimeString(),
        ]);
    }

    public function test_update_order_rejects_overlapping_wo_on_same_line(): void
    {
        $line = $this->createLine();
        $existingStart = $this->mondayAt('08:00');
        $existingEnd = $this->mondayAt('10:00');
        $existing = $this->createWO($line, [
            'order_no' => 'WO-OCCUPYING',
            'planned_start_at' => $existingStart,
            'planned_end_at' => $existingEnd,
        ]);

        $candidate = $this->createWO($line, ['order_no' => 'WO-CANDIDATE']);

        $response = $this->actingAs($this->admin)->putJson(
            "/admin/schedule/{$candidate->id}",
            [
                'line_id' => $line->id,
                'planned_start_at' => $this->mondayAt('09:00')->toDateTimeString(),
                'planned_end_at' => $this->mondayAt('11:00')->toDateTimeString(),
            ]
        );

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'conflict' => true,
        ]);
        $response->assertJsonStructure(['message']);

        // The candidate must not have been mutated.
        $this->assertDatabaseHas('work_orders', [
            'id' => $candidate->id,
            'planned_start_at' => null,
            'planned_end_at' => null,
        ]);
    }

    public function test_update_order_force_conflict_bypasses_check(): void
    {
        $line = $this->createLine();
        $this->createWO($line, [
            'planned_start_at' => $this->mondayAt('08:00'),
            'planned_end_at' => $this->mondayAt('10:00'),
        ]);
        $candidate = $this->createWO($line);

        $start = $this->mondayAt('09:00')->toDateTimeString();
        $end = $this->mondayAt('11:00')->toDateTimeString();

        $response = $this->actingAs($this->admin)->putJson(
            "/admin/schedule/{$candidate->id}",
            [
                'line_id' => $line->id,
                'planned_start_at' => $start,
                'planned_end_at' => $end,
                'force_conflict' => 1,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('work_orders', [
            'id' => $candidate->id,
            'planned_start_at' => $start,
            'planned_end_at' => $end,
        ]);
    }

    public function test_update_order_allows_overlap_on_different_lines(): void
    {
        $lineA = $this->createLine(['name' => 'Line A']);
        $lineB = $this->createLine(['name' => 'Line B']);

        $this->createWO($lineA, [
            'planned_start_at' => $this->mondayAt('08:00'),
            'planned_end_at' => $this->mondayAt('10:00'),
        ]);
        $candidate = $this->createWO($lineB);

        $start = $this->mondayAt('09:00')->toDateTimeString();
        $end = $this->mondayAt('11:00')->toDateTimeString();

        $response = $this->actingAs($this->admin)->putJson(
            "/admin/schedule/{$candidate->id}",
            [
                'line_id' => $lineB->id,
                'planned_start_at' => $start,
                'planned_end_at' => $end,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('work_orders', [
            'id' => $candidate->id,
            'line_id' => $lineB->id,
            'planned_start_at' => $start,
            'planned_end_at' => $end,
        ]);
    }

    public function test_update_order_validates_end_after_start(): void
    {
        $line = $this->createLine();
        $wo = $this->createWO($line);

        $response = $this->actingAs($this->admin)->putJson(
            "/admin/schedule/{$wo->id}",
            [
                'line_id' => $line->id,
                'planned_start_at' => $this->mondayAt('10:00')->toDateTimeString(),
                'planned_end_at' => $this->mondayAt('09:00')->toDateTimeString(),
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('planned_end_at');
    }

    // ── resizeOrder() minute-level ───────────────────────────────────────────

    public function test_resize_order_accepts_minute_timestamps(): void
    {
        $line = $this->createLine();
        $wo = $this->createWO($line, [
            'planned_start_at' => $this->mondayAt('08:00'),
            'planned_end_at' => $this->mondayAt('09:00'),
        ]);

        $newStart = $this->mondayAt('08:00')->toDateTimeString();
        $newEnd = $this->mondayAt('12:30')->toDateTimeString();

        $response = $this->actingAs($this->admin)->putJson(
            "/admin/schedule/{$wo->id}/resize",
            [
                'planned_start_at' => $newStart,
                'planned_end_at' => $newEnd,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['order' => ['id', 'order_no', 'planned_start_at', 'planned_end_at']]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'planned_start_at' => $newStart,
            'planned_end_at' => $newEnd,
        ]);
    }

    public function test_resize_order_rejects_overlap(): void
    {
        $line = $this->createLine();
        // Occupies 08:00 → 10:00.
        $this->createWO($line, [
            'planned_start_at' => $this->mondayAt('08:00'),
            'planned_end_at' => $this->mondayAt('10:00'),
        ]);
        // Adjacent (10:00 → 11:00) — non-overlap initially.
        $candidate = $this->createWO($line, [
            'planned_start_at' => $this->mondayAt('10:00'),
            'planned_end_at' => $this->mondayAt('11:00'),
        ]);

        $response = $this->actingAs($this->admin)->putJson(
            "/admin/schedule/{$candidate->id}/resize",
            [
                // Shrink left edge to 09:00 — now overlaps existing 08:00-10:00.
                'planned_start_at' => $this->mondayAt('09:00')->toDateTimeString(),
                'planned_end_at' => $this->mondayAt('11:00')->toDateTimeString(),
            ]
        );

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'conflict' => true,
        ]);

        // Candidate not mutated.
        $this->assertDatabaseHas('work_orders', [
            'id' => $candidate->id,
            'planned_start_at' => $this->mondayAt('10:00')->toDateTimeString(),
            'planned_end_at' => $this->mondayAt('11:00')->toDateTimeString(),
        ]);
    }

    public function test_resize_order_force_conflict_bypasses_check(): void
    {
        $line = $this->createLine();
        $this->createWO($line, [
            'planned_start_at' => $this->mondayAt('08:00'),
            'planned_end_at' => $this->mondayAt('10:00'),
        ]);
        $candidate = $this->createWO($line, [
            'planned_start_at' => $this->mondayAt('10:00'),
            'planned_end_at' => $this->mondayAt('11:00'),
        ]);

        $start = $this->mondayAt('09:00')->toDateTimeString();
        $end = $this->mondayAt('11:00')->toDateTimeString();

        $response = $this->actingAs($this->admin)->putJson(
            "/admin/schedule/{$candidate->id}/resize",
            [
                'planned_start_at' => $start,
                'planned_end_at' => $end,
                'force_conflict' => 1,
            ]
        );

        $response->assertOk();
        $this->assertDatabaseHas('work_orders', [
            'id' => $candidate->id,
            'planned_start_at' => $start,
            'planned_end_at' => $end,
        ]);
    }

    // ── cross-midnight ───────────────────────────────────────────────────────
    // Clamping a cross-midnight window to the visible day is now a client-side
    // concern (helpers.js `hourlyLanes`). These cases assert the controller's
    // range query still ships such overlapping orders in the flat payload.

    public function test_cross_midnight_wo_ending_next_day_is_in_payload(): void
    {
        $line = $this->createLine();
        $monday = now()->startOfWeek();

        // Order runs Monday 22:00 → Tuesday 06:00 (8h); it touches the Monday view.
        $this->createWO($line, [
            'order_no' => 'WO-MIDNIGHT-END',
            'planned_start_at' => $monday->copy()->setTimeFromTimeString('22:00'),
            'planned_end_at' => $monday->copy()->addDay()->setTimeFromTimeString('06:00'),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule?view_mode=hourly&start_date='.$monday->format('Y-m-d'));

        $response->assertOk();
        $response->assertSee('WO-MIDNIGHT-END');

        $wos = $response->viewData('page')['props']['workOrders'];
        $this->assertNotNull(collect($wos)->firstWhere('order_no', 'WO-MIDNIGHT-END'));
    }

    public function test_cross_midnight_wo_starting_prev_day_is_in_payload(): void
    {
        $line = $this->createLine();
        $monday = now()->startOfWeek();

        // Order runs Sunday 22:00 → Monday 06:00 (8h); it touches the Monday view.
        $this->createWO($line, [
            'order_no' => 'WO-MIDNIGHT-START',
            'planned_start_at' => $monday->copy()->subDay()->setTimeFromTimeString('22:00'),
            'planned_end_at' => $monday->copy()->setTimeFromTimeString('06:00'),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule?view_mode=hourly&start_date='.$monday->format('Y-m-d'));

        $response->assertOk();
        $response->assertSee('WO-MIDNIGHT-START');

        $wos = $response->viewData('page')['props']['workOrders'];
        $this->assertNotNull(collect($wos)->firstWhere('order_no', 'WO-MIDNIGHT-START'));
    }

    // ── legacy (due-date only) order ─────────────────────────────────────────

    public function test_legacy_wo_with_due_date_only_is_in_payload(): void
    {
        $line = $this->createLine();
        $monday = now()->startOfWeek();

        $this->createWO($line, [
            'order_no' => 'WO-LEGACY',
            'due_date' => $monday->copy()->setTimeFromTimeString('12:00'),
            'planned_start_at' => null,
            'planned_end_at' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule?view_mode=hourly&start_date='.$monday->format('Y-m-d'));

        $response->assertOk();
        $response->assertSee('WO-LEGACY');

        // A due-date-only order is shipped in the payload (matched by due_date in
        // range) but carries no minute timestamps — the hourly timeline simply
        // won't lay it out (that is a client-side concern now).
        $wos = $response->viewData('page')['props']['workOrders'];
        $row = collect($wos)->firstWhere('order_no', 'WO-LEGACY');
        $this->assertNotNull($row);
        $this->assertNull($row['planned_start_at']);
        $this->assertNull($row['planned_end_at']);
    }

    // ── slot_minutes setting ─────────────────────────────────────────────────

    public function test_settings_can_change_slot_minutes(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'schedule_slot_minutes'],
            ['value' => '"30"', 'description' => 'test override']
        );

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule?view_mode=hourly');

        $response->assertOk();
        $this->assertSame(30, $response->viewData('page')['props']['slotMinutes']);
    }

    public function test_invalid_slot_minutes_falls_back_to_default(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'schedule_slot_minutes'],
            ['value' => '"7"', 'description' => 'invalid value']
        );

        $response = $this->actingAs($this->admin)
            ->get('/admin/schedule?view_mode=hourly');

        $response->assertOk();
        $this->assertSame(15, $response->viewData('page')['props']['slotMinutes']);
    }
}
