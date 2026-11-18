<?php

namespace Tests\Feature\Schedule\Insights;

use App\Models\Line;
use App\Models\PlanningInsightAcknowledgement;
use App\Models\ScheduleChangeLog;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStaffingSnapshot;
use App\Services\Schedule\Insights\StaffingSnapshots;
use App\Services\Schedule\SchedulePlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Edge cases of the planner insights found in review: how far "Move order"
 * moves an order, which change an undo reverts, what counts as a lost person,
 * two planners acting on the same insight, keys that survive switching views,
 * who may read the acknowledgement stream, and how often the board is counted.
 *
 * The plant: CNC Turning with Jan, Ewa and Adam, Assembly A with Marta and
 * Olga, Painting with nobody. Weekday shifts Morning 06–14 and Afternoon
 * 14–22, week of Monday 2026-11-23.
 */
class PlanningInsightReviewFixesTest extends TestCase
{
    use PlansWithStaff;
    use RefreshDatabase;

    private Line $cnc;

    private Line $asm;

    private Line $paint;

    /** @var array<string, User> */
    private array $people;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlant();
        $this->withAbsenceProvider();
        $this->cnc = $this->plantLine('CNC Turning', 'CNC');
        $this->asm = $this->plantLine('Assembly A', 'ASM');
        $this->paint = $this->plantLine('Painting', 'PNT');
        $this->people = [
            'jan' => $this->operator('Jan Kowalski', $this->cnc),
            'ewa' => $this->operator('Ewa Lis', $this->cnc),
            'adam' => $this->operator('Adam Nowak', $this->cnc),
            'marta' => $this->operator('Marta Nowak', $this->asm),
            'olga' => $this->operator('Olga Wrona', $this->asm),
        ];
    }

    private function act(User $user, string $action, array $body, array $query = [])
    {
        return $this->actingAs($user)->postJson("/admin/schedule/insights/{$action}", $this->board($query) + $body);
    }

    /** A 3-person order spanning from one shift cell to another, like a stretched card on the board. */
    private function spanning(string $from, int $fromShift, string $to, int $toShift, string $due = '2026-12-18'): WorkOrder
    {
        $order = $this->planned($this->cnc, $from, $fromShift, operators: 3, attrs: ['due_date' => $due]);
        $order->update([
            'end_date' => $to,
            'end_shift_number' => $toShift,
            'planned_end_at' => \Carbon\Carbon::parse($to)->addDay()->format('Y-m-d 00:00:00'),
        ]);

        return $order->fresh();
    }

    // ── 1. Move keeps the order's length in real shifts ─────────────────────

    public function test_move_keeps_the_number_of_real_shifts_when_the_board_has_a_shift_the_line_does_not_run(): void
    {
        // A night shift that only runs on Painting gives the board a third
        // column; CNC Turning runs shifts 1–2 of 3.
        Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '22:00', 'end_time' => '06:00', 'days_of_week' => [1, 2, 3, 4, 5], 'line_id' => $this->paint->id, 'is_active' => true, 'sort_order' => 3]);
        // Tue morning → Wed afternoon: four real shifts (Tue 1, Tue 2, Wed 1, Wed 2).
        $order = $this->spanning('2026-11-24', 1, '2026-11-25', 2);
        $this->absent($this->people['jan'], '2026-11-24');
        $admin = $this->planner();

        $this->act($admin, 'move', ['insight_key' => 'short:'.$this->cnc->id.':2026-11-24:1', 'work_order_id' => $order->id])->assertOk();

        $order->refresh();
        // Four real shifts again: Wed 1, Wed 2, Thu 1, Thu 2 — not five.
        $this->assertSame('2026-11-25 06:00', $order->planned_start_at->format('Y-m-d H:i'));
        $this->assertSame(['2026-11-26', 2], [$order->end_date->toDateString(), $order->end_shift_number]);
    }

    public function test_move_over_a_weekend_does_not_count_the_shiftless_days(): void
    {
        // Fri morning → Mon afternoon: four real shifts around a weekend without shifts.
        $order = $this->spanning('2026-11-27', 1, '2026-11-30', 2);
        $this->absent($this->people['jan'], '2026-11-27');
        $admin = $this->planner();

        $this->act($admin, 'move', ['insight_key' => 'short:'.$this->cnc->id.':2026-11-27:1', 'work_order_id' => $order->id])->assertOk();

        $order->refresh();
        // Mon 1, Mon 2, Tue 1, Tue 2.
        $this->assertSame('2026-11-30 06:00', $order->planned_start_at->format('Y-m-d H:i'));
        $this->assertSame(['2026-12-01', 2], [$order->end_date->toDateString(), $order->end_shift_number]);
    }

    // ── 2. Undo reverts exactly the move it recorded ────────────────────────

    public function test_a_move_that_changes_nothing_is_refused_and_records_nothing(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1, operators: 3);
        $this->absent($this->people['jan'], '2026-11-24');
        // An older edit of the same order sits in the change log.
        ScheduleChangeLog::create(['work_order_id' => $order->id, 'action' => 'reschedule', 'before' => [], 'after' => []]);
        // The planner's move writes nothing (the order already sits where it would go).
        $this->partialMock(SchedulePlannerService::class, fn ($mock) => $mock->shouldReceive('updateOrder')
            ->andReturn(['conflict' => false, 'warnings' => [], 'cross_line_warnings' => [], 'change_log_id' => null]));

        $this->act($this->planner(), 'move', ['insight_key' => 'short:'.$this->cnc->id.':2026-11-24:1', 'work_order_id' => $order->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nothing to move: the order is already there.');

        $this->assertDatabaseCount('planning_insight_acknowledgements', 0);
    }

    public function test_the_acknowledgement_stores_the_change_the_move_itself_made(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1, operators: 3);
        $this->absent($this->people['jan'], '2026-11-24');

        $this->act($this->planner(), 'move', ['insight_key' => 'short:'.$this->cnc->id.':2026-11-24:1', 'work_order_id' => $order->id])->assertOk();

        $change = ScheduleChangeLog::where('work_order_id', $order->id)->sole();
        $this->assertSame($change->id, PlanningInsightAcknowledgement::sole()->payload['change_log_id']);
        $this->assertSame('2026-11-25', substr($change->after['planned_start_at'], 0, 10));
    }

    public function test_undo_is_refused_with_409_when_the_order_was_edited_since_even_if_it_came_back(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1, operators: 3);
        $this->absent($this->people['jan'], '2026-11-24');
        $admin = $this->planner();
        $this->act($admin, 'move', ['insight_key' => 'short:'.$this->cnc->id.':2026-11-24:1', 'work_order_id' => $order->id])->assertOk();
        $ack = PlanningInsightAcknowledgement::sole();
        // Someone moves it to Thursday and back to where the insight put it.
        $there = ['planned_start_at' => '2026-11-25T06:00:00', 'shift_number' => 1, 'end_date' => '2026-11-25', 'end_shift_number' => 1, 'planned_end_at' => '2026-11-26T00:00:00'];
        $this->actingAs($admin)->putJson("/admin/schedule/{$order->id}", ['planned_start_at' => '2026-11-26T06:00:00', 'end_date' => '2026-11-26', 'planned_end_at' => '2026-11-27T00:00:00'])->assertOk();
        $this->actingAs($admin)->putJson("/admin/schedule/{$order->id}", $there)->assertOk();

        $this->actingAs($admin)->postJson("/admin/schedule/insights/acknowledgements/{$ack->id}/undo", $this->board())
            ->assertStatus(409);

        $this->assertSame('2026-11-25', $order->fresh()->planned_start_at->toDateString());
        $this->assertNotSoftDeleted($ack);
        $this->assertSame(3, ScheduleChangeLog::where('work_order_id', $order->id)->whereNull('undone_at')->count());
    }

    // ── 3. Only people on the snapshot roster can be lost ───────────────────

    public function test_a_substitute_added_after_planning_and_absent_later_is_not_a_loss(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1);
        app(StaffingSnapshots::class)->capture($order);
        $this->assertNotNull(WorkOrderStaffingSnapshot::where('work_order_id', $order->id)->first());

        // Marta joins CNC Turning as a substitute, then turns out to be away.
        $this->cnc->users()->attach($this->people['marta']->id);
        $this->absent($this->people['marta'], '2026-11-24');

        $state = $this->drawer($this->planner());

        $this->assertNull($this->insight($state, 'staffing_changed'));
        $this->assertNull(app(StaffingSnapshots::class)->losses($order->fresh(), WorkOrderStaffingSnapshot::where('work_order_id', $order->id)->first()));
    }

    public function test_someone_on_the_snapshot_roster_who_becomes_absent_is_still_a_loss(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1);
        app(StaffingSnapshots::class)->capture($order);
        $this->cnc->users()->attach($this->people['marta']->id);
        $this->absent($this->people['marta'], '2026-11-24');
        $this->absent($this->people['ewa'], '2026-11-24');

        $insight = $this->insight($this->drawer($this->planner()), 'staffing_changed');

        $this->assertStringContainsString('Ewa Lis absent on Tue 24.11', $insight['why']);
        $this->assertStringNotContainsString('Marta', $insight['why']);
    }

    // ── 4. Two planners on the same insight ─────────────────────────────────

    public function test_a_second_acknowledgement_answers_409_with_who_handled_it(): void
    {
        $this->planned($this->cnc, '2026-11-24', 1, operators: 4);
        $key = 'short:'.$this->cnc->id.':2026-11-24:1';
        $this->act($this->planner(), 'acknowledge', ['insight_key' => $key])->assertOk();

        $this->act($this->planner('Supervisor'), 'acknowledge', ['insight_key' => $key])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Already handled by Admin User.');

        $this->assertSame(1, PlanningInsightAcknowledgement::withTrashed()->count());
    }

    public function test_a_move_after_someone_acknowledged_does_not_move_the_order(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1, operators: 3);
        $this->absent($this->people['jan'], '2026-11-24');
        $key = 'short:'.$this->cnc->id.':2026-11-24:1';
        $this->act($this->planner(), 'acknowledge', ['insight_key' => $key])->assertOk();

        $this->act($this->planner('Supervisor'), 'move', ['insight_key' => $key, 'work_order_id' => $order->id])->assertStatus(409);

        $this->assertSame('2026-11-24', $order->fresh()->planned_start_at->toDateString());
        $this->assertSame(0, ScheduleChangeLog::count());
    }

    public function test_a_concurrent_insert_of_the_same_key_answers_409_and_rolls_the_move_back(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1, operators: 3);
        $this->absent($this->people['jan'], '2026-11-24');
        $key = 'short:'.$this->cnc->id.':2026-11-24:1';
        $other = $this->planner('Supervisor');
        // Another planner's row lands between our check and our insert.
        $raced = false;
        PlanningInsightAcknowledgement::creating(function () use (&$raced, $key, $other) {
            if ($raced) {
                return;
            }
            $raced = true;
            DB::table('planning_insight_acknowledgements')->insert([
                'insight_key' => $key, 'insight_type' => 'short_shift', 'action' => 'acknowledge', 'title' => 'x', 'summary' => 'x',
                'acknowledged_by_id' => $other->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->act($this->planner(), 'move', ['insight_key' => $key, 'work_order_id' => $order->id])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertTrue($raced);
        $this->assertSame('2026-11-24', $order->fresh()->planned_start_at->toDateString(), 'the move rolled back with the insert');
        $this->assertSame(0, ScheduleChangeLog::count());
    }

    // ── 5. Keys survive switching views ─────────────────────────────────────

    public function test_an_untracked_line_acknowledged_in_the_weekly_view_stays_resolved_in_the_daily_view(): void
    {
        $this->planned($this->paint, '2026-11-23', 1);
        $this->planned($this->paint, '2026-11-25', 1);
        $admin = $this->planner();
        $weekly = $this->insight($this->drawer($admin), 'untracked_line');
        $this->act($admin, 'acknowledge', ['insight_key' => $weekly['key']])->assertOk();

        $daily = $this->drawer($admin, ['view_mode' => 'daily', 'start_date' => '2026-11-25']);
        $this->assertNull($this->insight($daily, 'untracked_line'));
        $midweek = $this->drawer($admin, ['start_date' => '2026-11-25']);
        $this->assertNull($this->insight($midweek, 'untracked_line'));

        // The daily view computes the same key for the same week.
        $this->cnc->users()->detach();
        $this->paint->users()->detach();
        $other = $this->drawer($admin, ['view_mode' => 'daily', 'start_date' => '2026-11-23']);
        $this->assertNull($this->insight($other, 'untracked_line'));
        $nextWeek = $this->planned($this->paint, '2026-12-01', 1);
        $next = $this->insight($this->drawer($admin, ['view_mode' => 'daily', 'start_date' => '2026-12-01']), 'untracked_line');
        $this->assertNotSame($weekly['key'], $next['key']);
        $this->assertSame([$nextWeek->id], array_column($next['affected'], 'work_order_id'));
    }

    public function test_a_single_skill_insight_acknowledged_in_the_weekly_view_stays_resolved_in_the_daily_view(): void
    {
        $this->withSkillProvider([7 => 'TIG welding']);
        $weld = $this->plantLine('Welding', 'WLD');
        $tomasz = $this->operator('Tomasz Lis', $weld);
        $this->operator('Piotr Wójcik', $weld);
        $this->skills = [$tomasz->worker_id => [7]];
        $this->planned($weld, '2026-11-24', 1, skills: [7]);
        $this->planned($weld, '2026-11-26', 1, skills: [7]);
        $admin = $this->planner();
        $weekly = $this->insight($this->drawer($admin), 'single_skill');
        $this->act($admin, 'acknowledge', ['insight_key' => $weekly['key']])->assertOk();

        $this->assertNull($this->insight($this->drawer($admin, ['view_mode' => 'daily', 'start_date' => '2026-11-26']), 'single_skill'));
        $this->assertNull($this->insight($this->drawer($admin, ['view_mode' => 'daily', 'start_date' => '2026-11-24']), 'single_skill'));
    }

    // ── 8. Operators never read the acknowledgement stream ──────────────────

    public function test_an_operator_with_the_schedule_tab_cannot_read_acknowledgements(): void
    {
        $this->planned($this->cnc, '2026-11-24', 1, operators: 4);
        $this->act($this->planner(), 'acknowledge', ['insight_key' => 'short:'.$this->cnc->id.':2026-11-24:1'])->assertOk();
        $operator = $this->people['jan'];
        $operator->givePermissionTo(Permission::findOrCreate('tab:schedule', 'web'));

        $this->actingAs($operator)->getJson('/api/collections/planning_insight_acknowledgements')->assertForbidden();
        $this->actingAs($this->planner('Supervisor'))->getJson('/api/collections/planning_insight_acknowledgements')->assertOk();
    }

    // ── 9. Every assignment path refreshes the planners ─────────────────────

    public function test_api_assignment_paths_touch_the_line(): void
    {
        $admin = $this->planner();
        $token = $admin->createToken('test')->plainTextToken;
        $bump = function (string $method, string $url, array $body, Line ...$lines) use ($token) {
            foreach ($lines as $line) {
                Line::whereKey($line->id)->update(['updated_at' => '2026-01-01 00:00:00']);
            }
            $this->withToken($token)->json($method, $url, $body)->assertOk();
            foreach ($lines as $line) {
                $this->assertTrue($line->fresh()->updated_at->greaterThan('2026-01-01 00:00:00'), "{$method} {$url} touched {$line->name}");
            }
        };

        $bump('POST', "/api/v1/lines/{$this->asm->id}/users", ['user_ids' => [$this->people['marta']->id, $this->people['jan']->id]], $this->asm);
        $bump('DELETE', "/api/v1/lines/{$this->asm->id}/users/{$this->people['jan']->id}", [], $this->asm);
        $bump('POST', "/api/v1/users/{$this->people['marta']->id}/lines", ['line_ids' => [$this->cnc->id]], $this->cnc, $this->asm);
    }

    // ── 10. One count per refresh ───────────────────────────────────────────

    public function test_the_planner_page_leaves_the_staffing_count_to_the_insights_endpoint(): void
    {
        $this->planned($this->cnc, '2026-11-24', 1, operators: 4);

        $this->actingAs($this->planner())->get('/admin/schedule?view_mode=weekly&start_date=2026-11-23')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canSeeInsights', true)->missing('staffing'));
    }

    public function test_the_insights_endpoint_query_count_does_not_grow_with_short_cells(): void
    {
        $admin = $this->planner();
        $this->planned($this->cnc, '2026-11-24', 1, operators: 4);
        $small = $this->countQueries(fn () => $this->drawer($admin));

        foreach (['2026-11-23', '2026-11-25', '2026-11-26', '2026-11-27'] as $date) {
            foreach ([1, 2] as $shift) {
                $this->planned($this->cnc, $date, $shift, operators: 4);
                $this->planned($this->asm, $date, $shift, operators: 3);
            }
        }
        $large = $this->countQueries(fn () => $this->drawer($admin));

        $this->assertLessThanOrEqual(60, $small, 'one count of the board');
        $this->assertLessThanOrEqual($small + 6, $large, '17 short cells cost (almost) nothing more than one');
    }

    private function countQueries(callable $fn): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $fn();
        $n = $count;
        $count = -100000; // later listeners of this test do not matter

        return $n;
    }
}
