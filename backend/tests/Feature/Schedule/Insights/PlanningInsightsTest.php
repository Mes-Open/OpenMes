<?php

namespace Tests\Feature\Schedule\Insights;

use App\Models\Line;
use App\Models\PlanningInsightAcknowledgement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Production Planner's staffing badges and insights drawer: every insight
 * type, with and without a workforce provider (test doubles of the provider
 * contracts), and who may see them.
 */
class PlanningInsightsTest extends TestCase
{
    use PlansWithStaff;
    use RefreshDatabase;

    private Line $cnc;

    private Line $asm;

    private Line $paint;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlant();
    }

    /** CNC Turning: Jan, Ewa, Adam. Assembly A: Marta, Olga. Painting: nobody. */
    private function plant(): array
    {
        $this->cnc = $this->plantLine('CNC Turning', 'CNC');
        $this->asm = $this->plantLine('Assembly A', 'ASM');
        $this->paint = $this->plantLine('Painting', 'PNT');

        return [
            'jan' => $this->operator('Jan Kowalski', $this->cnc),
            'ewa' => $this->operator('Ewa Lis', $this->cnc),
            'adam' => $this->operator('Adam Nowak', $this->cnc),
            'marta' => $this->operator('Marta Nowak', $this->asm),
            'olga' => $this->operator('Olga Wrona', $this->asm),
        ];
    }

    // ── a) a shift short of people ──────────────────────────────────────────

    public function test_a_shift_short_because_of_an_absence_names_who_and_offers_fixes(): void
    {
        $this->withAbsenceProvider();
        $people = $this->plant();
        $this->absent($people['jan'], '2026-11-24');
        $order = $this->planned($this->cnc, '2026-11-24', 1, operators: 3);

        $state = $this->drawer($this->planner());

        $cell = $this->cellOf($state, $this->cnc, '2026-11-24', 1);
        $this->assertSame(2, $cell['available']);
        $this->assertSame(3, $cell['needed']);
        $this->assertTrue($cell['short']);
        $this->assertSame(['Jan Kowalski'], $cell['absent']);
        $this->assertSame(1, $state['staffing']['days']['2026-11-24']['absent']);
        $this->assertArrayNotHasKey('2026-11-23', $state['staffing']['days']);

        $insight = $this->insight($state, 'short_shift');
        $this->assertSame('short:'.$this->cnc->id.':2026-11-24:1', $insight['key']);
        $this->assertSame('bad', $insight['severity']);
        $this->assertSame('people', $insight['category']);
        $this->assertStringContainsString('Absent: Jan Kowalski.', $insight['why']);
        $this->assertSame(['needed' => 3, 'available' => 2, 'present' => 2, 'spare_elsewhere' => 2], $insight['numbers']);
        $this->assertSame([['line_id' => $this->cnc->id, 'date' => '2026-11-24', 'shift' => 1]], $insight['cells']);
        $this->assertSame($order->id, $insight['affected'][0]['work_order_id']);
        $this->assertSame('2026-11-27', $insight['affected'][0]['due_date']);
        $this->assertFalse($insight['affected'][0]['late']);

        // Best first: a substitute covers it; the move lands on the next full shift.
        $this->assertSame('substitute', $insight['actions'][0]['kind']);
        $this->assertTrue($insight['actions'][0]['best']);
        $this->assertSame($people['marta']->id, $insight['actions'][0]['user_id']);
        $move = collect($insight['actions'])->firstWhere('kind', 'move');
        $this->assertStringContainsString('Wed 25.11 Morning', $move['label']);
        $this->assertContains('acknowledge', array_column($insight['actions'], 'kind'));
        $this->assertSame(1, $state['drawer']['summary']['needs_action']);
    }

    public function test_core_alone_reports_a_short_shift_without_absences(): void
    {
        $this->plant();
        $this->planned($this->cnc, '2026-11-24', 1, operators: 4);

        $state = $this->drawer($this->planner());

        $insight = $this->insight($state, 'short_shift');
        $this->assertSame('Needs 4, 3 available', $insight['meta']);
        $this->assertSame([], $state['staffing']['days']);
        $this->assertSame([], $this->cellOf($state, $this->cnc, '2026-11-24', 1)['absent']);
        $this->assertNull($this->insight($state, 'single_skill'));
        $this->assertFalse($state['drawer']['provider']['skills']);
    }

    public function test_a_count_only_provider_still_gets_badges_and_short_shifts(): void
    {
        $this->plant();
        $this->withCountOnlyProvider(2);
        $this->planned($this->cnc, '2026-11-24', 1, operators: 3);

        $state = $this->drawer($this->planner());

        $this->assertSame(2, $this->cellOf($state, $this->cnc, '2026-11-24', 1)['available']);
        $this->assertSame('short_shift', $this->insight($state, 'short_shift')['type']);
        $this->assertFalse($state['staffing']['tracks_absences']);
        $this->assertNull($this->insight($state, 'shared_operator'));
    }

    public function test_spare_and_exactly_enough_shifts_raise_nothing(): void
    {
        $this->plant();
        $this->planned($this->cnc, '2026-11-23', 1, operators: 3);
        $this->planned($this->asm, '2026-11-23', 1, operators: 1);

        $state = $this->drawer($this->planner());

        $this->assertSame([], $state['drawer']['insights']);
        $this->assertSame([3, 3], [$this->cellOf($state, $this->cnc, '2026-11-23', 1)['available'], $this->cellOf($state, $this->cnc, '2026-11-23', 1)['needed']]);
        $this->assertSame([2, 1], [$this->cellOf($state, $this->asm, '2026-11-23', 1)['available'], $this->cellOf($state, $this->asm, '2026-11-23', 1)['needed']]);
    }

    // ── b) one operator on two lines ────────────────────────────────────────

    public function test_work_on_both_lines_of_a_shared_operator_names_the_person_and_the_other_line(): void
    {
        $turn = $this->plantLine('CNC Turning', 'CNC');
        $asm = $this->plantLine('Assembly A', 'ASM');
        $this->operator('Piotr Wójcik', $turn, $asm);
        $this->planned($turn, '2026-11-23', 1);
        $this->planned($asm, '2026-11-23', 1);

        $state = $this->drawer($this->planner());

        $cell = $this->cellOf($state, $turn, '2026-11-23', 1);
        $this->assertSame(1, $cell['present']);
        $this->assertSame(0, $cell['available'], 'one person is counted once across lines');
        $this->assertSame([['line_id' => $asm->id, 'line_name' => 'Assembly A', 'names' => ['Piotr Wójcik']]], $cell['held_elsewhere']);

        $shared = collect($state['drawer']['insights'])->where('type', 'shared_operator')->keyBy('line_id');
        $this->assertCount(2, $shared);
        $this->assertSame('Piotr Wójcik also works on Assembly A', $shared[$turn->id]['meta']);
        $this->assertStringContainsString('Piotr Wójcik is booked on Assembly A at that time', $shared[$turn->id]['why']);
        $this->assertSame('warn', $shared[$turn->id]['severity']);
        $this->assertNull($this->insight($state, 'short_shift'));
    }

    public function test_a_shared_operator_free_on_the_other_line_is_simply_present(): void
    {
        $turn = $this->plantLine('CNC Turning', 'CNC');
        $asm = $this->plantLine('Assembly A', 'ASM');
        $this->operator('Piotr Wójcik', $turn, $asm);
        $this->planned($turn, '2026-11-23', 1);
        $this->planned($asm, '2026-11-23', 2);

        $state = $this->drawer($this->planner());

        $this->assertSame(1, $this->cellOf($state, $turn, '2026-11-23', 1)['available']);
        $this->assertSame([], $state['drawer']['insights']);
    }

    // ── c) one qualified person ─────────────────────────────────────────────

    public function test_a_skill_only_one_person_holds_is_flagged_when_the_provider_records_skills(): void
    {
        $this->withSkillProvider([7 => 'TIG welding']);
        $weld = $this->plantLine('Welding', 'WLD');
        $tomasz = $this->operator('Tomasz Lis', $weld);
        $this->operator('Piotr Wójcik', $weld);
        $other = $this->plantLine('Frames', 'FRM');
        $backup = $this->operator('Ewa Mazur', $other);
        $this->skills = [$tomasz->worker_id => [7], $backup->worker_id => [7]];
        $order = $this->planned($weld, '2026-11-25', 1, skills: [7]);

        $state = $this->drawer($this->planner());

        $insight = $this->insight($state, 'single_skill');
        $this->assertSame('skills', $insight['category']);
        $this->assertSame('TIG welding depends on one person', $insight['title']);
        $this->assertSame('Tomasz Lis is the only qualified operator on Welding', $insight['meta']);
        $this->assertSame($order->id, $insight['affected'][0]['work_order_id']);
        $this->assertSame([['line_id' => $weld->id, 'date' => '2026-11-25', 'shift' => 1]], $insight['cells']);
        $this->assertSame($backup->id, $insight['actions'][0]['user_id'], 'someone qualified elsewhere is offered');
        $this->assertTrue($state['drawer']['provider']['skills']);
    }

    public function test_no_skill_insight_without_skills_recorded(): void
    {
        $weld = $this->plantLine('Welding', 'WLD');
        $this->operator('Tomasz Lis', $weld);
        $this->planned($weld, '2026-11-25', 1, skills: [7]);

        $this->assertNull($this->insight($this->drawer($this->planner()), 'single_skill'));
    }

    // ── d) a line nobody is assigned to ─────────────────────────────────────

    public function test_work_on_a_line_without_operators_is_not_tracked(): void
    {
        $this->plant();
        $a = $this->planned($this->paint, '2026-11-23', 2);
        $b = $this->planned($this->paint, '2026-11-26', 1);

        $state = $this->drawer($this->planner());

        $cell = $this->cellOf($state, $this->paint, '2026-11-23', 2);
        $this->assertFalse($cell['tracked']);
        $this->assertNull($cell['available']);
        $insight = $this->insight($state, 'untracked_line');
        $this->assertSame('info', $insight['severity']);
        $this->assertSame('plan', $insight['category']);
        $this->assertSame('Painting is planned without people', $insight['title']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_column($insight['affected'], 'work_order_id'));
        $this->assertSame('/admin/lines/'.$this->paint->id.'#operators', $insight['actions'][0]['href']);
        $this->assertCount(2, $insight['cells']);
        $this->assertSame(1, $state['drawer']['summary']['to_watch']);
    }

    public function test_an_untracked_line_without_work_raises_nothing(): void
    {
        $this->plant();

        $this->assertSame([], $this->drawer($this->planner())['drawer']['insights']);
    }

    // ── e) staffing changed after planning ──────────────────────────────────

    /** Plan an order through the planner itself, which records its staffing. */
    private function planThroughPlanner(User $planner, Line $line, string $date, int $shift = 1): WorkOrder
    {
        $order = $this->planned($line, $date, $shift);
        $order->update(['line_id' => null, 'planned_start_at' => null, 'planned_end_at' => null, 'shift_number' => null, 'end_date' => null, 'end_shift_number' => null]);
        $this->actingAs($planner)->putJson("/admin/schedule/{$order->id}", [
            'line_id' => $line->id, 'planned_start_at' => "{$date}T06:00:00", 'shift_number' => $shift,
            'week_number' => '', 'end_date' => $date, 'end_shift_number' => $shift,
            'planned_end_at' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d\T00:00:00'),
        ])->assertOk()->assertJson(['success' => true]);

        return $order->fresh();
    }

    public function test_an_order_that_lost_an_operator_and_got_an_absence_says_what_changed(): void
    {
        $this->withAbsenceProvider();
        $people = $this->plant();
        $admin = $this->planner();
        $order = $this->planThroughPlanner($admin, $this->cnc, '2026-11-24');
        $this->assertDatabaseHas('work_order_staffing_snapshots', ['work_order_id' => $order->id]);
        $this->assertNull($this->insight($this->drawer($admin), 'staffing_changed'));

        $this->cnc->users()->detach($people['adam']->id);
        $this->absent($people['jan'], '2026-11-24');

        $insight = $this->insight($this->drawer($admin), 'staffing_changed');
        $this->assertSame('changed:'.$order->id, $insight['key']);
        $this->assertStringContainsString('Jan Kowalski absent on Tue 24.11', $insight['why']);
        $this->assertStringContainsString('Adam Nowak no longer on CNC Turning', $insight['why']);
        $this->assertSame($order->id, $insight['affected'][0]['work_order_id']);
    }

    public function test_more_people_since_planning_is_not_a_change_worth_reporting(): void
    {
        $this->plant();
        $admin = $this->planner();
        $this->planThroughPlanner($admin, $this->cnc, '2026-11-24');

        $this->operator('New Hire', $this->cnc);

        $this->assertNull($this->insight($this->drawer($admin), 'staffing_changed'));
    }

    // ── who sees it ─────────────────────────────────────────────────────────

    public function test_the_planner_page_offers_insights_to_planners_only(): void
    {
        $this->plant();
        $this->planned($this->cnc, '2026-11-24', 1, operators: 4);

        // The badges themselves come from the insights endpoint (one count per refresh).
        $this->actingAs($this->planner())->get('/admin/schedule?view_mode=weekly&start_date=2026-11-23')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/schedule/Planner')
                ->where('canSeeInsights', true)
                ->missing('staffing'));
        $this->assertTrue(collect($this->drawer($this->planner())['staffing']['cells'])->contains(fn ($c) => $c['line_id'] === $this->cnc->id && $c['short']));

        $this->actingAs($this->planner('Operator'))->get('/admin/schedule?view_mode=weekly&start_date=2026-11-23')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canSeeInsights', false)->missing('staffing'));
    }

    public function test_a_supervisor_with_the_schedule_tab_sees_the_drawer(): void
    {
        $this->plant();
        $this->planned($this->cnc, '2026-11-24', 1, operators: 4);

        $this->assertNotNull($this->insight($this->drawer($this->planner('Supervisor')), 'short_shift'));
    }

    public function test_guests_and_operators_never_get_the_drawer(): void
    {
        $this->getJson('/admin/schedule/insights?'.http_build_query($this->board()))->assertUnauthorized();
        $this->actingAs($this->planner('Operator'))->getJson('/admin/schedule/insights?'.http_build_query($this->board()))->assertForbidden();
    }

    public function test_the_drawer_validates_its_board(): void
    {
        $admin = $this->planner();

        $this->actingAs($admin)->getJson('/admin/schedule/insights?view_mode=yearly')->assertUnprocessable()->assertJsonValidationErrors('view_mode');
        $this->actingAs($admin)->getJson('/admin/schedule/insights?start_date=23.11.2026')->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->actingAs($admin)->getJson('/admin/schedule/insights?line_id=999')->assertUnprocessable()->assertJsonValidationErrors('line_id');
    }

    public function test_the_line_filter_limits_the_drawer_but_shared_people_still_count(): void
    {
        $turn = $this->plantLine('CNC Turning', 'CNC');
        $asm = $this->plantLine('Assembly A', 'ASM');
        $this->operator('Piotr Wójcik', $turn, $asm);
        $this->planned($turn, '2026-11-23', 1);
        $this->planned($asm, '2026-11-23', 1);

        $state = $this->drawer($this->planner(), ['line_id' => $turn->id]);

        $this->assertSame([$turn->id], array_values(array_unique(array_column($state['staffing']['cells'], 'line_id'))));
        $this->assertSame([$turn->id], array_column($state['drawer']['insights'], 'line_id'));
        $this->assertSame(0, $this->cellOf($state, $turn, '2026-11-23', 1)['available']);
    }

    public function test_insights_stay_inside_the_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('Admin');
        $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $adminB->assignRole('Admin');

        $this->actingAs($adminA);
        $this->plant();
        $this->planned($this->cnc, '2026-11-24', 1, operators: 4);

        $this->assertNotNull($this->insight($this->drawer($adminA), 'short_shift'));
        $stateB = $this->drawer($adminB);
        $this->assertSame([], $stateB['drawer']['insights']);
        $this->assertSame([], $stateB['staffing']['cells']);
    }

    public function test_the_drawer_defaults_to_this_week_and_has_no_monthly_view(): void
    {
        $admin = $this->planner();

        $this->actingAs($admin)->getJson('/admin/schedule/insights')->assertOk()
            ->assertJsonPath('drawer.range', ['start' => '2026-11-23', 'end' => '2026-11-29']);
        $this->actingAs($admin)->getJson('/admin/schedule/insights?view_mode=monthly')->assertUnprocessable();
    }

    public function test_the_daily_view_covers_one_day(): void
    {
        $this->plant();
        $this->planned($this->cnc, '2026-11-24', 1, operators: 4);
        $this->planned($this->cnc, '2026-11-25', 1, operators: 4);

        $state = $this->drawer($this->planner(), ['view_mode' => 'daily', 'start_date' => '2026-11-24']);

        $this->assertSame(['2026-11-24'], array_values(array_unique(array_column($state['staffing']['cells'], 'date'))));
        $this->assertCount(1, collect($state['drawer']['insights'])->where('type', 'short_shift'));
    }

    public function test_acknowledgements_are_soft_deletable_and_registered(): void
    {
        $this->assertSame(PlanningInsightAcknowledgement::class, \App\Support\SoftDeleteRegistry::MODELS['planning_insight_acknowledgements']);
        $ack = PlanningInsightAcknowledgement::factory()->create();
        $ack->delete();
        $this->assertSoftDeleted($ack);
        // The key is unique among live rows only.
        PlanningInsightAcknowledgement::factory()->create(['insight_key' => $ack->insight_key]);
        $this->assertSame(2, PlanningInsightAcknowledgement::withTrashed()->where('insight_key', $ack->insight_key)->count());
    }
}
