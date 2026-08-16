<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\ScheduleChangeLog;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile planner's API surface. These endpoints share
 * SchedulePlannerService with the Inertia web planner, so the audit-log and
 * extra-placement assertions here are what stop the two surfaces drifting
 * apart again.
 */
class SchedulePlannerApiTest extends TestCase
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

    // ---- board ----------------------------------------------------------

    public function test_supervisor_can_read_the_planner_board(): void
    {
        $line = Line::factory()->create();
        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'due_date' => now()->startOfWeek()->format('Y-m-d'),
            'status' => WorkOrder::STATUS_PENDING,
        ]);

        $r = $this->auth('Supervisor')->getJson('/api/v1/schedule/board?view_mode=weekly');

        $r->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'workOrders', 'lines', 'allLines', 'shifts', 'viewMode',
                    'shiftsPerDay', 'slotMinutes', 'startDate', 'rangeStart',
                    'rangeEnd', 'navPrev', 'navNext', 'backlogOrders',
                    'maintenanceEvents', 'realtimeMode', 'overdueImportant',
                ],
            ])
            ->assertJsonPath('data.viewMode', 'weekly');

        $this->assertContains($wo->id, array_column($r->json('data.workOrders'), 'id'));
    }

    public function test_board_start_date_anchors_the_visible_week(): void
    {
        $r = $this->auth('Admin')->getJson('/api/v1/schedule/board?view_mode=weekly&start_date=2026-03-11');

        // Weekly snaps to the ISO week start (Monday) of the requested date.
        $r->assertOk()->assertJsonPath('data.startDate', '2026-03-09');
    }

    public function test_board_can_filter_to_one_line(): void
    {
        $keep = Line::factory()->create();
        $other = Line::factory()->create();

        $r = $this->auth('Admin')->getJson('/api/v1/schedule/board?line_id='.$keep->id);

        $r->assertOk()->assertJsonCount(1, 'data.lines');
        $this->assertSame($keep->id, $r->json('data.lines.0.id'));
        // allLines stays unfiltered so the client can still render the picker.
        $this->assertContains($other->id, array_column($r->json('data.allLines'), 'id'));
    }

    public function test_board_rejects_an_unknown_view_mode(): void
    {
        $this->auth('Admin')->getJson('/api/v1/schedule/board?view_mode=bogus')
            ->assertStatus(422)
            ->assertJsonValidationErrors('view_mode');
    }

    public function test_board_rejects_guests_and_operators(): void
    {
        $this->getJson('/api/v1/schedule/board')->assertStatus(401);
        $this->auth('Operator')->getJson('/api/v1/schedule/board')->assertStatus(403);
    }

    // ---- update ---------------------------------------------------------

    public function test_update_moves_an_order_and_writes_an_audit_entry(): void
    {
        $from = Line::factory()->create();
        $to = Line::factory()->create();
        $wo = WorkOrder::factory()->create(['line_id' => $from->id, 'status' => WorkOrder::STATUS_PENDING]);

        $this->auth('Supervisor')->putJson('/api/v1/schedule/'.$wo->id, [
            'line_id' => $to->id,
            'due_date' => '2026-03-10',
            'shift_number' => 2,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame($to->id, $wo->fresh()->line_id);

        // The audit entry is what makes a mobile edit undoable from either
        // surface — the old API mirror skipped this entirely.
        $log = ScheduleChangeLog::where('work_order_id', $wo->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('reschedule', $log->action);
        $this->assertSame($from->id, $log->before['line_id']);
        $this->assertSame($to->id, $log->after['line_id']);
    }

    public function test_update_only_touches_fields_the_client_sent(): void
    {
        $line = Line::factory()->create();
        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'due_date' => '2026-03-10',
            'shift_number' => 3,
            'status' => WorkOrder::STATUS_PENDING,
        ]);

        // A partial edit must not null the placement fields it omitted.
        $this->auth('Admin')->putJson('/api/v1/schedule/'.$wo->id, ['due_date' => '2026-03-11'])
            ->assertOk();

        $wo->refresh();
        $this->assertSame('2026-03-11', $wo->due_date->format('Y-m-d'));
        $this->assertSame(3, $wo->shift_number);
        $this->assertSame($line->id, $wo->line_id);
    }

    public function test_update_syncs_extra_placements(): void
    {
        $a = Line::factory()->create();
        $b = Line::factory()->create();
        $wo = WorkOrder::factory()->create(['line_id' => $a->id, 'status' => WorkOrder::STATUS_PENDING]);

        $this->auth('Admin')->putJson('/api/v1/schedule/'.$wo->id, [
            'line_id' => $a->id,
            'due_date' => '2026-03-10',
            'extra_placements' => [
                ['line_id' => $b->id, 'due_date' => '2026-03-11', 'shift_number' => 1],
            ],
        ])->assertOk();

        $this->assertCount(1, $wo->fresh()->extraPlacements);
        $this->assertSame($b->id, $wo->fresh()->extraPlacements->first()->line_id);
    }

    public function test_unassigning_the_primary_line_clears_extra_placements(): void
    {
        $a = Line::factory()->create();
        $b = Line::factory()->create();
        $wo = WorkOrder::factory()->create(['line_id' => $a->id, 'status' => WorkOrder::STATUS_PENDING]);
        $wo->extraPlacements()->create(['line_id' => $b->id, 'due_date' => '2026-03-11', 'shift_number' => 1]);

        $this->auth('Admin')->putJson('/api/v1/schedule/'.$wo->id, ['line_id' => null])->assertOk();

        $this->assertCount(0, $wo->fresh()->extraPlacements);
    }

    public function test_update_rejects_an_unknown_line(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $this->auth('Admin')->putJson('/api/v1/schedule/'.$wo->id, ['line_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('line_id');
    }

    public function test_update_returns_409_on_a_minute_overlap_and_forces_through(): void
    {
        $line = Line::factory()->create();
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => '2026-03-10 08:00:00',
            'planned_end_at' => '2026-03-10 12:00:00',
        ]);
        $wo = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_PENDING]);

        $payload = [
            'line_id' => $line->id,
            'planned_start_at' => '2026-03-10 10:00:00',
            'planned_end_at' => '2026-03-10 14:00:00',
        ];

        $this->auth('Supervisor')->putJson('/api/v1/schedule/'.$wo->id, $payload)
            ->assertStatus(409)
            ->assertJsonPath('conflict', true);

        $this->assertNull($wo->fresh()->planned_start_at);

        $this->auth('Supervisor')->putJson('/api/v1/schedule/'.$wo->id, $payload + ['force_conflict' => true])
            ->assertOk();

        $this->assertNotNull($wo->fresh()->planned_start_at);
    }

    public function test_update_rejects_guests_and_operators(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $this->putJson('/api/v1/schedule/'.$wo->id, ['due_date' => '2026-03-10'])->assertStatus(401);
        $this->auth('Operator')->putJson('/api/v1/schedule/'.$wo->id, ['due_date' => '2026-03-10'])->assertStatus(403);
    }

    // ---- resize ---------------------------------------------------------

    public function test_resize_sets_the_minute_window(): void
    {
        $line = Line::factory()->create();
        $wo = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_PENDING]);

        $this->auth('Admin')->putJson('/api/v1/schedule/'.$wo->id.'/resize', [
            'planned_start_at' => '2026-03-10 08:00:00',
            'planned_end_at' => '2026-03-10 10:00:00',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNotNull($wo->fresh()->planned_end_at);
        $this->assertDatabaseHas('schedule_change_logs', ['work_order_id' => $wo->id, 'action' => 'reschedule']);
    }

    public function test_resize_requires_end_after_start(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $this->auth('Admin')->putJson('/api/v1/schedule/'.$wo->id.'/resize', [
            'planned_start_at' => '2026-03-10 10:00:00',
            'planned_end_at' => '2026-03-10 08:00:00',
        ])->assertStatus(422)->assertJsonValidationErrors('planned_end_at');
    }

    // ---- changes + undo -------------------------------------------------

    public function test_changes_lists_recent_edits(): void
    {
        $from = Line::factory()->create();
        $to = Line::factory()->create();
        $wo = WorkOrder::factory()->create(['line_id' => $from->id, 'status' => WorkOrder::STATUS_PENDING]);

        $this->auth('Admin')->putJson('/api/v1/schedule/'.$wo->id, ['line_id' => $to->id])->assertOk();

        $this->auth('Admin')->getJson('/api/v1/schedule/changes')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'work_order_id', 'order_no', 'action', 'before', 'after', 'user', 'created_at']]])
            ->assertJsonPath('data.0.work_order_id', $wo->id)
            ->assertJsonPath('data.0.action', 'reschedule');
    }

    public function test_undo_restores_the_previous_placement_and_is_itself_logged(): void
    {
        $from = Line::factory()->create();
        $to = Line::factory()->create();
        $wo = WorkOrder::factory()->create([
            'line_id' => $from->id,
            'due_date' => '2026-03-10',
            'status' => WorkOrder::STATUS_PENDING,
        ]);

        $this->auth('Admin')->putJson('/api/v1/schedule/'.$wo->id, [
            'line_id' => $to->id,
            'due_date' => '2026-03-12',
        ])->assertOk();

        $change = ScheduleChangeLog::latest('id')->first();

        $this->auth('Admin')->postJson('/api/v1/schedule/changes/'.$change->id.'/undo')
            ->assertOk()
            ->assertJsonPath('success', true);

        $wo->refresh();
        $this->assertSame($from->id, $wo->line_id);
        $this->assertSame('2026-03-10', $wo->due_date->format('Y-m-d'));

        $this->assertNotNull($change->fresh()->undone_at);
        // The undo is logged too, so it can itself be undone.
        $this->assertDatabaseHas('schedule_change_logs', ['work_order_id' => $wo->id, 'action' => 'undo']);
    }

    public function test_undo_of_a_deleted_order_returns_410(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);
        $change = ScheduleChangeLog::create([
            'work_order_id' => $wo->id,
            'user_id' => null,
            'action' => 'reschedule',
            'before' => ['line_id' => null],
            'after' => ['line_id' => null],
        ]);
        $wo->delete();

        $this->auth('Admin')->postJson('/api/v1/schedule/changes/'.$change->id.'/undo')->assertStatus(410);
    }

    public function test_changes_and_undo_reject_operators(): void
    {
        $this->getJson('/api/v1/schedule/changes')->assertStatus(401);
        $this->auth('Operator')->getJson('/api/v1/schedule/changes')->assertStatus(403);
    }
}
