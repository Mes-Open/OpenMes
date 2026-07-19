<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for multi-line work-order placements: beyond its primary
 * placement (work_orders.line_id/due_date/...), an order can run any number
 * of extra coarse segments (work_order_placements) on other lines/dates —
 * a staircase across lines or concurrent runs.
 *
 * - PUT /admin/schedule/{wo} syncs the extra_placements array
 * - segments are scheduled independently of the primary
 * - unassigning the primary clears every segment
 * - the board payload ships placements and matches the line filter through them
 * - minute conflicts live on primary lines only (extras are coarse)
 */
class SchedulePlannerSecondaryLineTest extends TestCase
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

    private function createLine(): Line
    {
        return Line::factory()->create(['is_active' => true]);
    }

    private function createWO(Line $line, array $attrs = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => Carbon::now()->startOfWeek()->addDay(),
        ], $attrs));
    }

    public function test_admin_can_build_a_three_line_staircase(): void
    {
        $lineA = $this->createLine();
        $lineB = $this->createLine();
        $lineC = $this->createLine();
        $monday = Carbon::now()->startOfWeek();
        $wo = $this->createWO($lineA, ['due_date' => $monday]);

        $response = $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'line_id' => $lineA->id,
            'due_date' => $monday->format('Y-m-d'),
            'extra_placements' => [
                ['line_id' => $lineB->id, 'due_date' => $monday->copy()->addDay()->format('Y-m-d'), 'shift_number' => 1],
                ['line_id' => $lineC->id, 'due_date' => $monday->copy()->addDays(2)->format('Y-m-d'), 'shift_number' => 2],
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $wo->refresh();
        $this->assertSame($lineA->id, $wo->line_id);
        $this->assertCount(2, $wo->extraPlacements);
        $this->assertSame([$lineB->id, $lineC->id], $wo->extraPlacements->pluck('line_id')->all());
    }

    public function test_sync_updates_removes_and_creates_segments(): void
    {
        $lineA = $this->createLine();
        $lineB = $this->createLine();
        $lineC = $this->createLine();
        $monday = Carbon::now()->startOfWeek();
        $wo = $this->createWO($lineA);
        $keep = $wo->extraPlacements()->create(['line_id' => $lineB->id, 'due_date' => $monday, 'shift_number' => 1]);
        $drop = $wo->extraPlacements()->create(['line_id' => $lineB->id, 'due_date' => $monday->copy()->addDay(), 'shift_number' => 1]);

        $response = $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'extra_placements' => [
                // moved to Thursday shift 2
                ['id' => $keep->id, 'line_id' => $keep->line_id, 'due_date' => $monday->copy()->addDays(3)->format('Y-m-d'), 'shift_number' => 2],
                // new segment on line C
                ['line_id' => $lineC->id, 'due_date' => $monday->copy()->addDays(4)->format('Y-m-d')],
            ],
        ]);

        $response->assertOk();
        $wo->refresh();
        $this->assertCount(2, $wo->extraPlacements);
        $this->assertNull($wo->extraPlacements->firstWhere('id', $drop->id));
        $kept = $wo->extraPlacements->firstWhere('id', $keep->id);
        $this->assertSame($monday->copy()->addDays(3)->format('Y-m-d'), $kept->due_date->format('Y-m-d'));
        $this->assertSame(2, $kept->shift_number);
        // The primary placement was untouched by the segment-only edit.
        $this->assertNotNull($wo->due_date);
    }

    public function test_segment_line_must_exist_and_date_is_required(): void
    {
        $line = $this->createLine();
        $wo = $this->createWO($line);

        $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'extra_placements' => [['line_id' => 999999, 'due_date' => '2026-07-15']],
        ])->assertStatus(422)->assertJsonValidationErrors(['extra_placements.0.line_id']);

        $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'extra_placements' => [['line_id' => $line->id]],
        ])->assertStatus(422)->assertJsonValidationErrors(['extra_placements.0.due_date']);
    }

    public function test_unassigning_the_primary_line_clears_every_segment(): void
    {
        $lineA = $this->createLine();
        $lineB = $this->createLine();
        $wo = $this->createWO($lineA);
        $wo->extraPlacements()->create(['line_id' => $lineB->id, 'due_date' => Carbon::now(), 'shift_number' => 1]);

        $response = $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'line_id' => '',
            'due_date' => '',
        ]);

        $response->assertOk();
        $wo->refresh();
        $this->assertNull($wo->line_id);
        $this->assertCount(0, $wo->extraPlacements);
    }

    public function test_board_ships_placements_and_matches_the_line_filter(): void
    {
        $lineA = $this->createLine();
        $lineB = $this->createLine();
        $monday = Carbon::now()->startOfWeek();
        $wo = $this->createWO($lineA, ['due_date' => $monday->copy()->addDay()]);
        $p = $wo->extraPlacements()->create(['line_id' => $lineB->id, 'due_date' => $monday->copy()->addDays(3), 'shift_number' => 2]);

        // Filtered to line B, the order's only match is its extra segment.
        $response = $this->actingAs($this->admin)->get('/admin/schedule?'.http_build_query([
            'view_mode' => 'weekly',
            'start_date' => $monday->format('Y-m-d'),
            'line_id' => $lineB->id,
        ]));
        $response->assertOk();

        $shipped = collect($response->viewData('page')['props']['workOrders'])->firstWhere('id', $wo->id);
        $this->assertNotNull($shipped);
        $this->assertCount(1, $shipped['placements']);
        $this->assertSame($p->id, $shipped['placements'][0]['id']);
        $this->assertSame($lineB->id, $shipped['placements'][0]['line_id']);
        $this->assertSame($monday->copy()->addDays(3)->format('Y-m-d'), $shipped['placements'][0]['due_date']);
    }

    public function test_minute_conflicts_apply_to_primary_lines_only(): void
    {
        $lineA = $this->createLine();
        $lineB = $this->createLine();
        $day = Carbon::now()->format('Y-m-d');

        // Existing minute-planned order occupying line B directly.
        $this->createWO($lineB, [
            'due_date' => null,
            'planned_start_at' => "{$day} 08:00:00",
            'planned_end_at' => "{$day} 12:00:00",
        ]);

        // Order primary on A with a coarse extra segment on B — its minute
        // window lives on A only, so no conflict via the extra segment.
        $wo = $this->createWO($lineA, ['due_date' => null]);
        $wo->extraPlacements()->create(['line_id' => $lineB->id, 'due_date' => $day, 'shift_number' => 1]);

        $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'line_id' => $lineA->id,
            'planned_start_at' => "{$day} 09:00:00",
            'planned_end_at' => "{$day} 11:00:00",
        ])->assertOk()->assertJsonPath('success', true);

        // On the SAME primary line it must 409 (and force bypasses it).
        $other = $this->createWO($lineB, ['due_date' => null]);
        $this->actingAs($this->admin)->putJson("/admin/schedule/{$other->id}", [
            'line_id' => $lineB->id,
            'planned_start_at' => "{$day} 09:00:00",
            'planned_end_at' => "{$day} 11:00:00",
        ])->assertStatus(409)->assertJsonPath('conflict', true);

        $this->actingAs($this->admin)->putJson("/admin/schedule/{$other->id}", [
            'line_id' => $lineB->id,
            'planned_start_at' => "{$day} 09:00:00",
            'planned_end_at' => "{$day} 11:00:00",
            'force_conflict' => 1,
        ])->assertOk();
    }

    public function test_capacity_counts_extra_segments_on_their_own_dates(): void
    {
        $lineA = $this->createLine();
        $lineB = $this->createLine();
        $monday = Carbon::now()->startOfWeek();

        // Primary far outside the window; extra segment inside it on line B.
        $wo = $this->createWO($lineA, ['due_date' => $monday->copy()->addWeeks(6)]);
        $wo->extraPlacements()->create(['line_id' => $lineB->id, 'due_date' => $monday->copy()->addDays(2), 'shift_number' => 1]);

        $grid = app(\App\Services\Schedule\CapacityService::class)
            ->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');

        $rowB = collect($grid['resources'])->firstWhere('id', $lineB->id);
        $cell = collect($rowB['cells'])->first();
        $this->assertTrue(
            ($cell['planned_h'] ?? 0) > 0 || ($cell['unestimated_count'] ?? 0) > 0,
            'The extra segment must contribute to line B\'s capacity cell.'
        );
    }

    public function test_guest_cannot_update(): void
    {
        $lineA = $this->createLine();
        $lineB = $this->createLine();
        $wo = $this->createWO($lineA);

        $this->putJson("/admin/schedule/{$wo->id}", [
            'extra_placements' => [['line_id' => $lineB->id, 'due_date' => '2026-07-15']],
        ])->assertUnauthorized();

        $this->assertCount(0, $wo->fresh()->extraPlacements);
    }

    public function test_operator_cannot_update(): void
    {
        $lineA = $this->createLine();
        $lineB = $this->createLine();
        $wo = $this->createWO($lineA);

        $this->actingAs($this->operator)->putJson("/admin/schedule/{$wo->id}", [
            'extra_placements' => [['line_id' => $lineB->id, 'due_date' => '2026-07-15']],
        ])->assertStatus(403);

        $this->assertCount(0, $wo->fresh()->extraPlacements);
    }
}
