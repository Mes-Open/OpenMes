<?php

namespace Tests\Feature\Schedule\Finite;

use App\Enums\ScheduledOperationStatus;
use App\Extension\Contracts\Null\NullWorkforceProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkstationType;
use App\Services\Schedule\Finite\ScheduleSimulationService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Staff presence and absence scenarios run through the real scheduler and
 * core's assigned-staff provider. Absences come from a stand-in workforce
 * provider keyed by date, the way a workforce module would report them.
 *
 * Calendar: Monday 2026-10-12, one global 06:00–14:00 weekday shift.
 */
class WorkforceScenariosTest extends TestCase
{
    use RefreshDatabase;
    use SchedulesFixtures;

    /** @var array<string, list<int>> date => absent worker ids */
    private array $absences = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchedule();

        $test = $this;
        $this->app->bind(WorkforceProvider::class, fn () => new class($test) extends NullWorkforceProvider
        {
            public function __construct(private WorkforceScenariosTest $test) {}

            public function absentWorkerIds(CarbonInterface $date): array
            {
                return $this->test->absentOn($date->toDateString());
            }
        });
    }

    /** @return list<int> */
    public function absentOn(string $date): array
    {
        return $this->absences[$date] ?? [];
    }

    /** @param  list<Worker>  $workers */
    private function absent(array $workers, string ...$dates): void
    {
        foreach ($dates as $date) {
            $this->absences[$date] = [...($this->absences[$date] ?? []), ...array_map(fn (Worker $w) => $w->id, $workers)];
        }
    }

    /** A line with $stations interchangeable machines and $people workers on it. */
    private function cell(int $stations, int $people): array
    {
        $line = $this->line();
        $type = WorkstationType::factory()->create();
        $first = null;
        for ($i = 0; $i < $stations; $i++) {
            $station = $this->station($line, $type);
            $first ??= $station;
        }
        $workers = $people > 0 ? Worker::factory()->count($people)->create(['workstation_id' => $first->id])->all() : [];

        return [$line, $type, $workers];
    }

    private function job(Line $line, WorkstationType $type, int $minutes, array $step = [], array $attrs = [])
    {
        return $this->order($line, [['estimated_duration_minutes' => $minutes, 'workstation_type_id' => $type->id] + $step], $attrs);
    }

    private function start(array $result, $order): mixed
    {
        return $this->opsFor($result, $order)[0]['planned_start_at'];
    }

    private function opStatus(array $result, $order): ScheduledOperationStatus
    {
        return $this->opsFor($result, $order)[0]['status'];
    }

    // ── Presence ────────────────────────────────────────────────────────────

    public function test_full_crew_runs_every_machine_in_parallel(): void
    {
        [$line, $type] = $this->cell(stations: 3, people: 3);
        $orders = [$this->job($line, $type, 60), $this->job($line, $type, 60), $this->job($line, $type, 60)];

        $result = $this->schedule();

        foreach ($orders as $order) {
            $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $order));
        }
    }

    public function test_fewer_people_than_machines_serialises_the_surplus(): void
    {
        [$line, $type] = $this->cell(stations: 3, people: 2);
        $a = $this->job($line, $type, 60, attrs: ['priority' => 3]);
        $b = $this->job($line, $type, 60, attrs: ['priority' => 2]);
        $c = $this->job($line, $type, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b));
        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($result, $c));
    }

    public function test_committed_work_outside_the_run_holds_its_operators(): void
    {
        [$line, $type] = $this->cell(stations: 2, people: 1);
        // Already planned by hand: Monday 06:00–10:00.
        $this->job($line, $type, 240, attrs: [
            'planned_start_at' => $this->at('2026-10-12 06:00'),
            'planned_end_at' => $this->at('2026-10-12 10:00'),
        ]);
        $new = $this->job($line, $type, 60);

        $this->assertEquals($this->at('2026-10-12 10:00'), $this->start($this->schedule(), $new));
    }

    // ── Absence ─────────────────────────────────────────────────────────────

    public function test_one_absence_pushes_a_two_person_step_to_the_next_full_day(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 2);
        $this->absent([$workers[0]], '2026-10-12');
        $order = $this->job($line, $type, 60, ['required_operators' => 2]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-13 06:00'), $this->start($result, $order));
        $this->assertSame(ScheduledOperationStatus::Scheduled, $this->opStatus($result, $order));
    }

    public function test_one_absence_still_lets_a_one_person_step_run(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 2);
        $this->absent([$workers[0]], '2026-10-12');
        $order = $this->job($line, $type, 60);

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($this->schedule(), $order));
    }

    public function test_whole_crew_absent_moves_work_to_the_first_staffed_day(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 2);
        $this->absent($workers, '2026-10-12', '2026-10-13');
        $order = $this->job($line, $type, 60);

        $this->assertEquals($this->at('2026-10-14 06:00'), $this->start($this->schedule(), $order));
    }

    public function test_absence_across_the_whole_horizon_reports_workforce_not_machines(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->absent($workers, '2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16');
        $order = $this->job($line, $type, 60);

        $result = $this->schedule(['end' => $this->at('2026-10-17 00:00')]);

        $this->assertSame(ScheduledOperationStatus::InfeasibleWorkforceCapacity, $this->opStatus($result, $order));
        $this->assertContains('workforce_capacity', array_column($this->opsFor($result, $order)[0]['reasons'], 'code'));
    }

    public function test_step_needing_more_people_than_the_line_has_is_workforce_infeasible(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 2);
        $order = $this->job($line, $type, 60, ['required_operators' => 3]);

        $this->assertSame(ScheduledOperationStatus::InfeasibleWorkforceCapacity, $this->opStatus($this->schedule(), $order));
    }

    public function test_multi_day_step_waits_for_a_window_with_no_absence_in_it(): void
    {
        // 16 h of work = Monday + Tuesday shifts; the only operator is away on Tuesday.
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->absent($workers, '2026-10-13');
        $order = $this->job($line, $type, 960);

        $result = $this->schedule();

        // The one-day absence moves the whole two-day step to Wednesday.
        $this->assertEquals($this->at('2026-10-14 06:00'), $this->start($result, $order));
    }

    public function test_absence_of_the_only_qualified_operator_blocks_skilled_work_that_day(): void
    {
        $this->recordSkills();
        [$line, $type, [$qualified, $other]] = $this->cell(stations: 1, people: 2);
        $skill = $this->line(); // stands in for a skill row
        $qualified->skills()->attach($skill->id);
        $this->absent([$qualified], '2026-10-12');
        $order = $this->job($line, $type, 60, ['required_skill_ids' => [$skill->id]]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-13 06:00'), $this->start($result, $order));
    }

    public function test_qualified_operator_absent_all_horizon_reports_qualification(): void
    {
        $this->recordSkills();
        [$line, $type, [$qualified]] = $this->cell(stations: 1, people: 2);
        $skill = $this->line();
        $qualified->skills()->attach($skill->id);
        $this->absent([$qualified], '2026-10-12', '2026-10-13');
        $order = $this->job($line, $type, 60, ['required_skill_ids' => [$skill->id]]);

        $result = $this->schedule(['end' => $this->at('2026-10-14 00:00')]);

        $this->assertSame(ScheduledOperationStatus::InfeasibleQualification, $this->opStatus($result, $order));
    }

    // ── Night shifts: an absence counts against the shift starting that day ──

    /** Replace the day shift with a 22:00–06:00 night shift on $days. */
    private function nightsOnly(array $days = [1, 2, 3, 4, 5], string $start = '22:00'): void
    {
        Shift::query()->update(['is_active' => false]);
        Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => $start, 'end_time' => '06:00', 'days_of_week' => $days, 'is_active' => true]);
    }

    public function test_night_shift_started_before_an_absence_day_is_not_blocked(): void
    {
        $this->nightsOnly();
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        // Away on Tuesday — i.e. Tuesday's night shift, not Monday's.
        $this->absent($workers, '2026-10-13');
        $order = $this->job($line, $type, 480);

        $result = $this->schedule();
        $op = $this->opsFor($result, $order)[0];

        $this->assertEquals($this->at('2026-10-12 22:00'), $op['planned_start_at']);
        $this->assertEquals($this->at('2026-10-13 06:00'), $op['planned_end_at']);
        $this->assertSame(ScheduledOperationStatus::Scheduled, $op['status']);
    }

    public function test_monday_absence_blocks_the_monday_night_shift(): void
    {
        $this->nightsOnly();
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->absent($workers, '2026-10-12');
        $order = $this->job($line, $type, 480);

        $this->assertEquals($this->at('2026-10-13 22:00'), $this->start($this->schedule(), $order));
    }

    public function test_monday_absence_does_not_block_the_sunday_night_shift(): void
    {
        $this->nightsOnly([7, 1, 2, 3, 4, 5]);
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->absent($workers, '2026-10-12');
        $order = $this->job($line, $type, 480);

        $op = $this->opsFor($this->schedule(['start' => $this->at('2026-10-11 22:00')]), $order)[0];

        $this->assertEquals($this->at('2026-10-11 22:00'), $op['planned_start_at']);
        $this->assertEquals($this->at('2026-10-12 06:00'), $op['planned_end_at']);
    }

    public function test_day_shift_absence_semantics_are_unchanged(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->absent($workers, '2026-10-13');
        $order = $this->job($line, $type, 60);
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($this->schedule(), $order));

        $this->absences = [];
        $this->absent($workers, '2026-10-12');
        $this->assertEquals($this->at('2026-10-13 06:00'), $this->start($this->schedule(), $order));
    }

    public function test_two_night_step_is_not_blocked_by_an_absence_on_the_morning_it_ends(): void
    {
        $this->nightsOnly();
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        // Wednesday's own night is not used: the step ends Wednesday 06:00.
        $this->absent($workers, '2026-10-14');
        $order = $this->job($line, $type, 960);

        $op = $this->opsFor($this->schedule(), $order)[0];

        $this->assertEquals($this->at('2026-10-12 22:00'), $op['planned_start_at']);
        $this->assertEquals($this->at('2026-10-14 06:00'), $op['planned_end_at']);
    }

    public function test_two_night_step_skips_an_absent_night(): void
    {
        $this->nightsOnly();
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->absent($workers, '2026-10-13');
        $order = $this->job($line, $type, 960);

        $ops = $this->opsFor($this->schedule(), $order);

        $this->assertCount(1, $ops);
        $this->assertEquals($this->at('2026-10-14 22:00'), $ops[0]['planned_start_at']);
        $this->assertEquals($this->at('2026-10-16 06:00'), $ops[0]['planned_end_at']);
    }

    public function test_a_staffing_rejection_retries_at_the_next_shift_start_not_mid_shift(): void
    {
        // Night 22:30–06:00 runs straight into Day 06:00–14:00, so the calendar
        // reads one window; Monday's absence blocks Monday's night but not
        // Tuesday's day shift, which must start on time.
        $this->nightsOnly(start: '22:30');
        Shift::create(['name' => 'Day 2', 'code' => 'D2', 'start_time' => '06:00', 'end_time' => '14:00', 'days_of_week' => [1, 2, 3, 4, 5], 'is_active' => true]);
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->absent($workers, '2026-10-12');
        $order = $this->job($line, $type, 60);

        $this->assertEquals($this->at('2026-10-13 06:00'), $this->start($this->schedule(), $order));
    }

    public function test_an_absence_the_day_before_the_horizon_stales_a_night_shift_draft(): void
    {
        $this->nightsOnly([7, 1, 2, 3, 4, 5]);
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(['start' => $this->at('2026-10-12 00:00')]), $user);

        // Sunday's night shift runs into Monday, the first day of the horizon.
        $this->absent($workers, '2026-10-11');

        $this->assertSame('staffing', $service->staleReason($draft->fresh())['code']);
    }

    // ── Gap probes: what a planner would expect but the scheduler does not do ─

    public function test_probe_a_person_on_two_lines_is_not_booked_on_both_at_once(): void
    {
        $lineA = $this->line();
        $lineB = $this->line();
        $type = WorkstationType::factory()->create();
        $this->station($lineA, $type);
        $this->station($lineB, $type);
        $operator = User::factory()->create();
        $operator->lines()->attach([$lineA->id, $lineB->id]);
        $a = $this->job($lineA, $type, 60, attrs: ['priority' => 2]);
        $b = $this->job($lineB, $type, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($result, $b), 'one person cannot run both lines at 06:00');

        $waited = collect($this->opsFor($result, $b)[0]['reasons'])->firstWhere('code', 'waited_for_operators');
        $this->assertSame([['line_id' => $lineA->id, 'line_name' => $lineA->name, 'operators' => 1]], $waited['busy_lines']);
        $this->assertSame("Waited for operators busy on {$lineA->name}.", $waited['message']);
        $this->assertSame($this->at('2026-10-12 06:00')->toIso8601String(), $waited['window_start']);
        $this->assertSame(ScheduledOperationStatus::Scheduled, $this->opStatus($result, $b));
        $this->assertNull(collect($this->opsFor($result, $a)[0]['reasons'])->firstWhere('code', 'waited_for_operators'));
    }

    // ── One person, several lines: a shared pool ────────────────────────────

    /** A line with $stations machines of its own equipment class, so work never crosses lines. */
    private function lineWithStations(int $stations): array
    {
        $line = $this->line();
        $type = WorkstationType::factory()->create();
        $first = null;
        for ($i = 0; $i < $stations; $i++) {
            $first ??= $this->station($line, $type);
            if ($i > 0) {
                $this->station($line, $type);
            }
        }

        return [$line, $type, $first];
    }

    /** An operator account assigned to every given line. */
    private function sharedOperator(Line ...$lines): User
    {
        $user = User::factory()->create();
        $user->lines()->attach(array_map(fn (Line $l) => $l->id, $lines));

        return $user;
    }

    private function waitedReason(array $result, $order): ?array
    {
        return collect($this->opsFor($result, $order)[0]['reasons'])->firstWhere('code', 'waited_for_operators');
    }

    public function test_a_person_on_one_line_only_is_unchanged(): void
    {
        [$lineA, $typeA, $stationA] = $this->lineWithStations(1);
        [$lineB, $typeB, $stationB] = $this->lineWithStations(1);
        Worker::factory()->create(['workstation_id' => $stationA->id]);
        Worker::factory()->create(['workstation_id' => $stationB->id]);
        $a = $this->job($lineA, $typeA, 60, attrs: ['priority' => 2]);
        $b = $this->job($lineB, $typeB, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b));
        $this->assertNull($this->waitedReason($result, $b));
    }

    public function test_two_shared_and_one_own_operator_are_matched_across_lines(): void
    {
        [$lineA, $typeA, $stationA] = $this->lineWithStations(1);
        [$lineB, $typeB] = $this->lineWithStations(3);
        $this->sharedOperator($lineA, $lineB);
        $this->sharedOperator($lineA, $lineB);
        Worker::factory()->create(['workstation_id' => $stationA->id]); // line A's own
        $a = $this->job($lineA, $typeA, 60, attrs: ['priority' => 3]);
        $b1 = $this->job($lineB, $typeB, 60, attrs: ['priority' => 2]);
        $b2 = $this->job($lineB, $typeB, 60, attrs: ['priority' => 1]);
        $b3 = $this->job($lineB, $typeB, 60, attrs: ['priority' => 0]);

        $result = $this->schedule();

        // A takes its own operator so both shared ones can work B.
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b1));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b2));
        // Three people, three jobs at 06:00: the fourth waits.
        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($result, $b3));
    }

    public function test_shared_operators_held_on_the_busier_line_push_the_other_line(): void
    {
        [$lineA, $typeA, $stationA] = $this->lineWithStations(3);
        [$lineB, $typeB] = $this->lineWithStations(1);
        $this->sharedOperator($lineA, $lineB);
        $this->sharedOperator($lineA, $lineB);
        Worker::factory()->create(['workstation_id' => $stationA->id]);
        $as = [
            $this->job($lineA, $typeA, 60, attrs: ['priority' => 5]),
            $this->job($lineA, $typeA, 60, attrs: ['priority' => 4]),
            $this->job($lineA, $typeA, 60, attrs: ['priority' => 3]),
        ];
        $b = $this->job($lineB, $typeB, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        foreach ($as as $order) {
            $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $order));
        }
        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($result, $b));
        $this->assertSame([['line_id' => $lineA->id, 'line_name' => $lineA->name, 'operators' => 2]], $this->waitedReason($result, $b)['busy_lines']);
    }

    public function test_a_count_only_provider_keeps_each_line_separate(): void
    {
        $this->fakeWorkforce(1);
        [$lineA, $typeA] = $this->lineWithStations(1);
        [$lineB, $typeB] = $this->lineWithStations(1);
        $this->sharedOperator($lineA, $lineB);
        $a = $this->job($lineA, $typeA, 60, attrs: ['priority' => 2]);
        $b = $this->job($lineB, $typeB, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        // It cannot say who it counted, so each line keeps its own one operator.
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b));
        $this->assertNull($this->waitedReason($result, $b));
    }

    public function test_an_unplaced_reason_names_the_line_holding_shared_people(): void
    {
        [$lineA, $typeA] = $this->lineWithStations(1);
        [$lineB, $typeB] = $this->lineWithStations(1);
        $this->sharedOperator($lineA, $lineB);
        $this->job($lineA, $typeA, 480, attrs: ['priority' => 2]); // the whole Monday shift
        $b = $this->job($lineB, $typeB, 60, attrs: ['priority' => 1]);

        $result = $this->schedule(['end' => $this->at('2026-10-13 00:00')]);
        $reason = $this->peopleReason($result, $b);

        $this->assertSame(ScheduledOperationStatus::InfeasibleWorkforceCapacity, $this->opStatus($result, $b));
        $this->assertSame(1, $reason['available']);
        $this->assertSame(1, $reason['in_use']);
        $this->assertSame([['line_id' => $lineA->id, 'line_name' => $lineA->name, 'operators' => 1]], $reason['busy_lines']);
        $this->assertSame("Needs 1 operators, 1 available. 1 busy with other work on {$lineA->name}.", $reason['message']);
    }

    public function test_committed_work_on_another_line_holds_a_shared_operator(): void
    {
        [$lineA, $typeA] = $this->lineWithStations(1);
        [$lineB, $typeB] = $this->lineWithStations(1);
        $this->sharedOperator($lineA, $lineB);
        // Planned by hand on line A: Monday 06:00–10:00.
        $this->job($lineA, $typeA, 240, attrs: [
            'planned_start_at' => $this->at('2026-10-12 06:00'),
            'planned_end_at' => $this->at('2026-10-12 10:00'),
        ]);
        $b = $this->job($lineB, $typeB, 60);

        $this->assertEquals($this->at('2026-10-12 10:00'), $this->start($this->schedule(), $b));
    }

    public function test_an_absent_shared_operator_frees_nobody(): void
    {
        [$lineA, $typeA, $stationA] = $this->lineWithStations(1);
        [$lineB, $typeB] = $this->lineWithStations(1);
        $shared = Worker::factory()->create(['workstation_id' => $stationA->id]);
        User::factory()->create(['worker_id' => $shared->id])->lines()->attach($lineB->id);
        $this->absent([$shared], '2026-10-12');
        $a = $this->job($lineA, $typeA, 60, attrs: ['priority' => 2]);
        $b = $this->job($lineB, $typeB, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        // One worker (also an account on B) staffs both lines: away Monday, then one at a time.
        $this->assertEquals($this->at('2026-10-13 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-13 07:00'), $this->start($result, $b));
    }

    public function test_a_skilled_step_waits_for_its_only_qualified_person_held_on_another_line(): void
    {
        $this->recordSkills();
        [$lineA, $typeA, $stationA] = $this->lineWithStations(1);
        [$lineB, $typeB] = $this->lineWithStations(1);
        $skill = $this->line(); // stands in for a skill row
        // P holds the skill and staffs both lines (a worker on A, an account on B); Q works A only.
        $p = Worker::factory()->create(['workstation_id' => $stationA->id]);
        $p->skills()->attach($skill->id);
        User::factory()->create(['worker_id' => $p->id])->lines()->attach($lineB->id);
        Worker::factory()->create(['workstation_id' => $stationA->id]);
        $b = $this->job($lineB, $typeB, 60, attrs: ['priority' => 2]);
        $a = $this->job($lineA, $typeA, 60, ['required_skill_ids' => [$skill->id]], ['priority' => 1]);

        $result = $this->schedule();

        // B's only person is P, so B takes P at 06:00; Q is free but cannot do A's step.
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b));
        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($result, $a));
        $waited = collect($this->opsFor($result, $a)[0]['reasons'])->firstWhere('code', 'waited_for_qualified_operators');
        $this->assertSame([['line_id' => $lineB->id, 'line_name' => $lineB->name, 'operators' => 1]], $waited['busy_lines']);
        $this->assertSame("Waited: only 1 qualified operator for {$skill->name} ({$p->name}), busy with other work on {$lineB->name}.", $waited['message']);
        $this->assertNull($this->waitedReason($result, $a), 'the skilled reason replaces the generic one');
    }

    public function test_a_people_reason_separates_this_lines_own_work_from_other_lines(): void
    {
        [$lineA, $typeA] = $this->lineWithStations(2);
        [$lineL, $typeL, $stationL] = $this->lineWithStations(2);
        $this->sharedOperator($lineA, $lineL);
        $this->sharedOperator($lineA, $lineL);
        Worker::factory()->create(['workstation_id' => $stationL->id]); // line L's own
        $this->job($lineA, $typeA, 480, attrs: ['priority' => 5]);
        $this->job($lineA, $typeA, 480, attrs: ['priority' => 4]);
        $this->job($lineL, $typeL, 480, attrs: ['priority' => 3]);
        $late = $this->job($lineL, $typeL, 60, attrs: ['priority' => 1]);

        $reason = $this->peopleReason($this->schedule(['end' => $this->at('2026-10-13 00:00')]), $late);

        $this->assertSame(3, $reason['in_use']);
        $this->assertSame([['line_id' => $lineA->id, 'line_name' => $lineA->name, 'operators' => 2]], $reason['busy_lines']);
        $this->assertSame("Needs 1 operators, 3 available. 3 busy with other work, 2 of them on {$lineA->name}.", $reason['message']);
    }

    public function test_the_waited_reason_survives_the_shared_person_moving_on_to_this_line(): void
    {
        // As in the machine-shop example: A's last step runs on a bench of line B
        // itself, so the shared person goes from A's work to B's own bench.
        $lineA = $this->line();
        $lineB = $this->line();
        $lathe = $this->station($lineA);
        $saw = $this->station($lineB);
        $bench = $this->station($lineB);
        $this->sharedOperator($lineA, $lineB);
        $this->order($lineA, [
            ['estimated_duration_minutes' => 60, 'workstation_id' => $lathe->id],
            ['estimated_duration_minutes' => 30, 'workstation_id' => $bench->id],
        ], ['priority' => 2]);
        $b = $this->order($lineB, [['estimated_duration_minutes' => 10, 'workstation_id' => $saw->id]], ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 07:30'), $this->start($result, $b));
        $waited = $this->waitedReason($result, $b);
        $this->assertSame([['line_id' => $lineA->id, 'line_name' => $lineA->name, 'operators' => 1]], $waited['busy_lines']);
        $this->assertSame("Waited for operators busy on {$lineA->name}.", $waited['message']);
    }

    public function test_the_waited_reason_is_dropped_when_people_are_simply_away_later(): void
    {
        [$lineA, $typeA] = $this->lineWithStations(1);
        [$lineB, $typeB, $stationB] = $this->lineWithStations(1);
        $shared = Worker::factory()->create(['workstation_id' => $stationB->id]);
        User::factory()->create(['worker_id' => $shared->id])->lines()->attach($lineA->id);
        // A takes the shared worker all Monday; on Tuesday they are away.
        $this->job($lineA, $typeA, 480, attrs: ['priority' => 2]);
        $this->absent([$shared], '2026-10-13');
        $b = $this->job($lineB, $typeB, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-14 06:00'), $this->start($result, $b));
        $this->assertNull($this->waitedReason($result, $b), 'the last thing holding it was the absence');
    }

    public function test_the_waited_reason_is_dropped_when_something_else_pushed_the_step_last(): void
    {
        [$lineA, $typeA] = $this->lineWithStations(1);
        [$lineB, $typeB] = $this->lineWithStations(1);
        $this->sharedOperator($lineA, $lineB);
        // Line B is planned by hand 07:00–09:00, so after waiting at 06:00 the step hits that.
        $this->job($lineB, $typeB, 120, attrs: [
            'planned_start_at' => $this->at('2026-10-12 07:00'),
            'planned_end_at' => $this->at('2026-10-12 09:00'),
        ]);
        $this->job($lineA, $typeA, 60, attrs: ['priority' => 2]);
        $b = $this->job($lineB, $typeB, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 09:00'), $this->start($result, $b));
        $this->assertNull($this->waitedReason($result, $b));
    }

    public function test_the_shared_pool_adds_no_queries_per_order(): void
    {
        $lines = [];
        for ($i = 0; $i < 3; $i++) {
            $lines[] = $this->lineWithStations(2);
        }
        $this->sharedOperator(...array_column($lines, 0));
        $this->sharedOperator($lines[0][0], $lines[1][0]);
        Worker::factory()->create(['workstation_id' => $lines[2][2]->id]);

        $count = function (int $orders) use ($lines): int {
            \App\Models\WorkOrder::query()->delete();
            for ($i = 0; $i < $orders; $i++) {
                [$line, $type] = $lines[$i % 3];
                $this->job($line, $type, 60, attrs: ['priority' => $orders - $i]);
            }
            $queries = 0;
            $counting = true;
            \Illuminate\Support\Facades\DB::listen(function () use (&$queries, &$counting) {
                if ($counting) {
                    $queries++;
                }
            });
            $result = $this->schedule();
            $counting = false;
            $this->assertCount($orders, $result['operations']);
            $this->assertNotContains(false, array_map(fn ($op) => $op['status']->isScheduled(), $result['operations']));

            return $queries;
        };

        // Both runs queue work behind earlier work, so both read the changeover rules once.
        $few = $count(9);
        $many = $count(24);
        $this->assertSame($few, $many, 'scheduling 24 orders must not query more than scheduling 9');
    }

    // ── One person, one step at a time: people matched to skilled steps ────

    public function test_a_multi_skilled_operator_is_not_booked_for_two_skilled_steps_at_once(): void
    {
        $this->recordSkills();
        [$line, $type, [$both]] = $this->cell(stations: 2, people: 2);
        $skillA = $this->line();
        $skillB = $this->line();
        $both->skills()->attach([$skillA->id, $skillB->id]);
        $a = $this->job($line, $type, 60, ['required_skill_ids' => [$skillA->id]], ['priority' => 2]);
        $b = $this->job($line, $type, 60, ['required_skill_ids' => [$skillB->id]], ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($result, $b), 'only one person holds either skill');
        $waited = $this->qualifiedWaitReason($result, $b);
        $this->assertSame(1, $waited['available']);
        $this->assertSame([['name' => $both->name]], $waited['qualified_workers']);
        $this->assertSame([$skillB->id], $waited['required_skill_ids']);
        $this->assertSame("Waited: only 1 qualified operator for {$skillB->name} ({$both->name}), busy with other work.", $waited['message']);
        $this->assertSame($this->at('2026-10-12 06:00')->toIso8601String(), $waited['window_start']);
        $this->assertNull($this->qualifiedWaitReason($result, $a));
    }

    private function qualifiedWaitReason(array $result, $order): ?array
    {
        return collect($this->opsFor($result, $order)[0]['reasons'])->firstWhere('code', 'waited_for_qualified_operators');
    }

    public function test_two_operators_with_disjoint_skills_work_in_parallel(): void
    {
        $this->recordSkills();
        [$line, $type, [$x, $y]] = $this->cell(stations: 2, people: 2);
        $skillA = $this->line();
        $skillB = $this->line();
        $x->skills()->attach($skillA->id);
        $y->skills()->attach($skillB->id);
        $a = $this->job($line, $type, 60, ['required_skill_ids' => [$skillA->id]], ['priority' => 2]);
        $b = $this->job($line, $type, 60, ['required_skill_ids' => [$skillB->id]], ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b));
        $this->assertNull($this->qualifiedWaitReason($result, $b));
    }

    public function test_a_two_person_skilled_step_waits_for_a_holder_busy_on_another_skill(): void
    {
        $this->recordSkills();
        [$line, $type, [$x, $y]] = $this->cell(stations: 3, people: 3);
        $skillA = $this->line();
        $skillB = $this->line();
        // X holds A and B, Y holds A, the third holds nothing.
        $x->skills()->attach([$skillA->id, $skillB->id]);
        $y->skills()->attach($skillA->id);
        $first = $this->job($line, $type, 60, ['required_skill_ids' => [$skillB->id]], ['priority' => 2]);
        $pair = $this->job($line, $type, 60, ['required_operators' => 2, 'required_skill_ids' => [$skillA->id]], ['priority' => 1]);

        $result = $this->schedule();

        // B's step takes X, so at 06:00 only Y of the A holders is free.
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $first));
        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($result, $pair));
        $this->assertSame(2, $this->qualifiedWaitReason($result, $pair)['available']);
    }

    public function test_a_third_holder_lets_the_two_person_skilled_step_start_at_once(): void
    {
        $this->recordSkills();
        [$line, $type, [$x, $y, $w]] = $this->cell(stations: 3, people: 3);
        $skillA = $this->line();
        $skillB = $this->line();
        $x->skills()->attach([$skillA->id, $skillB->id]);
        $y->skills()->attach($skillA->id);
        $w->skills()->attach($skillA->id);
        $this->job($line, $type, 60, ['required_skill_ids' => [$skillB->id]], ['priority' => 2]);
        $pair = $this->job($line, $type, 60, ['required_operators' => 2, 'required_skill_ids' => [$skillA->id]], ['priority' => 1]);

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($this->schedule(), $pair));
    }

    public function test_a_two_person_skilled_step_with_one_holder_names_the_bottleneck(): void
    {
        $this->recordSkills();
        [$line, $type, [$jan]] = $this->cell(stations: 2, people: 2);
        $welding = $this->line(['name' => 'Welding']);
        $painting = $this->line(['name' => 'Painting']);
        $jan->skills()->attach([$welding->id, $painting->id]);
        $order = $this->job($line, $type, 60, ['required_operators' => 2, 'required_skill_ids' => [$painting->id]]);

        $result = $this->schedule(['end' => $this->at('2026-10-13 00:00')]);
        $reason = $this->peopleReason($result, $order, 'qualification');

        $this->assertSame(ScheduledOperationStatus::InfeasibleQualification, $this->opStatus($result, $order));
        $this->assertSame(['id' => $painting->id, 'name' => 'Painting'], $reason['bottleneck_skill']);
        $this->assertSame([['name' => $jan->name]], $reason['skill_holders']);
        $this->assertSame("Needs 2 operators qualified in Painting, 1 available. Painting is held only by {$jan->name}.", $reason['message']);
    }

    public function test_the_bottleneck_is_the_scarcest_of_the_required_skills(): void
    {
        $this->recordSkills();
        [$line, $type, [$x, $y, $z]] = $this->cell(stations: 1, people: 3);
        $common = $this->line(['name' => 'Common']);
        $rare = $this->line(['name' => 'Rare']);
        $x->skills()->attach([$common->id, $rare->id]);
        $y->skills()->attach($common->id);
        $z->skills()->attach($common->id);
        $order = $this->job($line, $type, 60, ['required_operators' => 2, 'required_skill_ids' => [$common->id, $rare->id]]);

        $reason = $this->peopleReason($this->schedule(['end' => $this->at('2026-10-13 00:00')]), $order, 'qualification');

        $this->assertSame(['id' => $rare->id, 'name' => 'Rare'], $reason['bottleneck_skill']);
        $this->assertStringEndsWith("Rare is held only by {$x->name}.", $reason['message']);
    }

    public function test_a_count_only_provider_keeps_the_old_skill_count(): void
    {
        $this->fakeWorkforce(2, qualified: 1);
        [$line, $type] = $this->cell(stations: 2, people: 0);
        $skillA = $this->line();
        $skillB = $this->line();
        $a = $this->job($line, $type, 60, ['required_skill_ids' => [$skillA->id]], ['priority' => 2]);
        $b = $this->job($line, $type, 60, ['required_skill_ids' => [$skillB->id]], ['priority' => 1]);

        $result = $this->schedule();

        // Without names it cannot tell the A holder from the B holder: counts as before.
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b));
        $this->assertNull($this->qualifiedWaitReason($result, $b));
        $this->assertSame([], $this->workforceOf($result)[$line->id]['single_holder_skills']);
    }

    public function test_without_recorded_skills_nothing_changes(): void
    {
        [$line, $type] = $this->cell(stations: 2, people: 2);
        $skill = $this->line();
        $a = $this->job($line, $type, 60, ['required_skill_ids' => [$skill->id]], ['priority' => 2]);
        $b = $this->job($line, $type, 60, ['required_skill_ids' => [$skill->id]], ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b));
        $this->assertSame([], $this->workforceOf($result)[$line->id]['single_holder_skills']);
    }

    public function test_an_unskilled_step_runs_beside_a_skilled_one(): void
    {
        $this->recordSkills();
        [$line, $type, [$x]] = $this->cell(stations: 2, people: 2);
        $skill = $this->line();
        $x->skills()->attach($skill->id);
        $skilled = $this->job($line, $type, 60, ['required_skill_ids' => [$skill->id]], ['priority' => 2]);
        $plain = $this->job($line, $type, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $skilled));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $plain));
    }

    public function test_an_unskilled_step_booked_first_leaves_the_holder_for_the_skilled_one(): void
    {
        $this->recordSkills();
        [$line, $type, [$x]] = $this->cell(stations: 2, people: 2);
        $skill = $this->line();
        $x->skills()->attach($skill->id);
        $plain = $this->job($line, $type, 60, attrs: ['priority' => 2]);
        $skilled = $this->job($line, $type, 60, ['required_skill_ids' => [$skill->id]], ['priority' => 1]);

        $result = $this->schedule();

        // The unskilled step can be anybody's, so X stays free for the skilled one.
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $plain));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $skilled));
    }

    public function test_an_unskilled_step_waits_when_only_the_busy_holder_is_left(): void
    {
        $this->recordSkills();
        [$line, $type, [$x, $y]] = $this->cell(stations: 3, people: 2);
        $skillA = $this->line();
        $skillB = $this->line();
        $x->skills()->attach([$skillA->id, $skillB->id]);
        $y->skills()->attach($skillB->id);
        // A takes X; B can only be Y then; nobody is left for the plain step.
        $this->job($line, $type, 60, ['required_skill_ids' => [$skillA->id]], ['priority' => 3]);
        $this->job($line, $type, 60, ['required_skill_ids' => [$skillB->id]], ['priority' => 2]);
        $plain = $this->job($line, $type, 60, attrs: ['priority' => 1]);

        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($this->schedule(), $plain));
    }

    public function test_a_multi_skilled_person_shared_by_two_lines_works_one_skilled_step_at_a_time(): void
    {
        $this->recordSkills();
        [$lineA, $typeA, $stationA] = $this->lineWithStations(1);
        [$lineB, $typeB] = $this->lineWithStations(1);
        $skillA = $this->line();
        $skillB = $this->line();
        // P holds both skills and staffs both lines; Q works line A only, unskilled.
        $p = Worker::factory()->create(['workstation_id' => $stationA->id]);
        $p->skills()->attach([$skillA->id, $skillB->id]);
        User::factory()->create(['worker_id' => $p->id])->lines()->attach($lineB->id);
        Worker::factory()->create(['workstation_id' => $stationA->id]);
        $a = $this->job($lineA, $typeA, 60, ['required_skill_ids' => [$skillA->id]], ['priority' => 2]);
        $b = $this->job($lineB, $typeB, 60, ['required_skill_ids' => [$skillB->id]], ['priority' => 1]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($result, $b));
        // Held on the other line: the skilled reason says who and where.
        $waited = $this->qualifiedWaitReason($result, $b);
        $this->assertSame([['line_id' => $lineA->id, 'line_name' => $lineA->name, 'operators' => 1]], $waited['busy_lines']);
        $this->assertSame([['name' => $p->name]], $waited['qualified_workers']);
        $this->assertNull($this->waitedReason($result, $b));
    }

    public function test_a_skilled_step_with_nobody_free_at_all_names_its_qualified_person(): void
    {
        // As in the machine-shop example: the only Welding holder and a second
        // account both staff the sawing and the turning line, and turning work
        // already takes them both.
        $this->recordSkills();
        [$saw, $sawType] = $this->lineWithStations(2);
        $turn = $this->line();
        $skill = $this->line(['name' => 'Welding']);
        $tomasz = Worker::factory()->create(['workstation_id' => null]);
        $tomasz->skills()->attach($skill->id);
        User::factory()->create(['worker_id' => $tomasz->id])->lines()->attach([$saw->id, $turn->id]);
        $this->sharedOperator($saw, $turn);
        $this->job($turn, WorkstationType::factory()->create(), 60, ['required_operators' => 2], [
            'planned_start_at' => $this->at('2026-10-12 06:00'),
            'planned_end_at' => $this->at('2026-10-12 07:00'),
        ]);
        $this->station($turn);
        $weld = $this->job($saw, $sawType, 30, ['required_skill_ids' => [$skill->id]]);

        $result = $this->schedule();

        $this->assertEquals($this->at('2026-10-12 07:00'), $this->start($result, $weld));
        $waited = $this->qualifiedWaitReason($result, $weld);
        $this->assertSame("Waited: only 1 qualified operator for Welding ({$tomasz->name}), busy with other work on {$turn->name}.", $waited['message']);
        $this->assertNull($this->waitedReason($result, $weld));
    }

    public function test_single_holder_skills_are_listed_only_on_lines_the_skilled_steps_can_run_on(): void
    {
        $this->recordSkills();
        [$saw, $sawType] = $this->lineWithStations(1);
        [$turn] = $this->lineWithStations(1);
        $skill = $this->line(['name' => 'Welding']);
        $tomasz = Worker::factory()->create(['workstation_id' => null]);
        $tomasz->skills()->attach($skill->id);
        User::factory()->create(['worker_id' => $tomasz->id])->lines()->attach([$saw->id, $turn->id]);
        $this->job($saw, $sawType, 30, ['required_skill_ids' => [$skill->id]]);

        $workforce = $this->workforceOf($this->schedule());

        // He is the only welder on both lines, but only sawing has welding work.
        $this->assertSame([['skill_id' => $skill->id, 'skill_name' => 'Welding', 'holder_name' => $tomasz->name]], $workforce[$saw->id]['single_holder_skills']);
        $this->assertSame([], $workforce[$turn->id]['single_holder_skills']);
    }

    public function test_workforce_section_lists_required_skills_with_a_single_holder(): void
    {
        $this->recordSkills();
        [$line, $type, [$x, $y]] = $this->cell(stations: 2, people: 2);
        $untracked = $this->line();
        $solo = $this->line(['name' => 'Welding']);
        $shared = $this->line(['name' => 'Painting']);
        $unused = $this->line(['name' => 'Gluing']);
        $x->skills()->attach([$solo->id, $shared->id, $unused->id]);
        $y->skills()->attach($shared->id);
        $this->job($line, $type, 60, ['required_skill_ids' => [$solo->id]]);
        $this->job($line, $type, 60, ['required_skill_ids' => [$shared->id]]);

        $workforce = $this->workforceOf($this->schedule());

        // Painting has two holders and nobody needs Gluing.
        $this->assertSame([['skill_id' => $solo->id, 'skill_name' => 'Welding', 'holder_name' => $x->name]], $workforce[$line->id]['single_holder_skills']);
        $this->assertSame([], $workforce[$untracked->id]['single_holder_skills']);
    }

    public function test_single_holder_skills_are_reported_when_people_are_ignored(): void
    {
        $this->recordSkills();
        [$line, $type, [$x]] = $this->cell(stations: 2, people: 2);
        $skillA = $this->line();
        $skillB = $this->line();
        $x->skills()->attach([$skillA->id, $skillB->id]);
        $a = $this->job($line, $type, 60, ['required_skill_ids' => [$skillA->id]], ['priority' => 2]);
        $b = $this->job($line, $type, 60, ['required_skill_ids' => [$skillB->id]], ['priority' => 1]);

        $result = $this->schedule(['respectWorkforce' => false]);

        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $b));
        $this->assertNull($this->qualifiedWaitReason($result, $b));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->start($result, $a));
        $this->assertCount(2, $this->workforceOf($result)[$line->id]['single_holder_skills']);
    }

    public function test_the_draft_keeps_single_holder_skills_and_the_wait_reason(): void
    {
        $this->recordSkills();
        [$line, $type, [$x]] = $this->cell(stations: 2, people: 2);
        $skillA = $this->line(['name' => 'Welding']);
        $skillB = $this->line(['name' => 'Painting']);
        $x->skills()->attach([$skillA->id, $skillB->id]);
        $this->job($line, $type, 60, ['required_skill_ids' => [$skillA->id]], ['priority' => 2]);
        $b = $this->job($line, $type, 60, ['required_skill_ids' => [$skillB->id]], ['priority' => 1]);

        $draft = app(ScheduleSimulationService::class)->generate($this->request(), User::factory()->create());

        $entry = collect($draft->fresh()->summary['workforce'])->keyBy('line_id')[$line->id];
        $this->assertSame(['Painting', 'Welding'], array_column($entry['single_holder_skills'], 'skill_name'));
        $reasons = $draft->operations()->where('work_order_id', $b->id)->sole()->reasons;
        $this->assertSame('waited_for_qualified_operators', collect($reasons)->firstWhere('code', 'waited_for_qualified_operators')['code']);
    }

    public function test_skilled_matching_adds_no_queries_per_order(): void
    {
        $this->recordSkills();
        [$line, $type, $workers] = $this->cell(stations: 2, people: 2);
        $skill = $this->line();
        foreach ($workers as $worker) {
            $worker->skills()->attach($skill->id);
        }

        $count = function (int $orders) use ($line, $type, $skill): int {
            \App\Models\WorkOrder::query()->delete();
            for ($i = 0; $i < $orders; $i++) {
                $this->job($line, $type, 60, ['required_skill_ids' => [$skill->id]], ['priority' => $orders - $i]);
            }
            $queries = 0;
            $counting = true;
            \Illuminate\Support\Facades\DB::listen(function () use (&$queries, &$counting) {
                if ($counting) {
                    $queries++;
                }
            });
            $result = $this->schedule();
            $counting = false;
            $this->assertNotContains(false, array_map(fn ($op) => $op['status']->isScheduled(), $result['operations']));

            return $queries;
        };

        // Both runs make orders wait for the two qualified people, so both name them once.
        $few = $count(9);
        $many = $count(24);
        $this->assertSame($few, $many, 'scheduling 24 skilled orders must not query more than scheduling 9');
    }

    // ── A draft goes stale when its staffing changes ────────────────────────

    private const STAFFING_CHANGED = 'Staffing changed since this draft was generated — generate it again.';

    private function assertApplyRefusedFor(ScheduleSimulationService $service, $draft, User $user, string $message): void
    {
        try {
            $service->apply($draft->fresh(), $user);
            $this->fail('Apply should have refused a stale draft.');
        } catch (\DomainException $e) {
            $this->assertSame($message, $e->getMessage());
        }
        $this->assertTrue($draft->fresh()->isDraft());
    }

    public function test_an_absence_recorded_after_the_draft_makes_it_stale(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(), $user);

        // The only operator calls in sick for Monday after the plan was drafted.
        $this->absent($workers, '2026-10-12');

        $this->assertSame('staffing', $service->staleReason($draft->fresh())['code']);
        $this->assertApplyRefusedFor($service, $draft, $user, self::STAFFING_CHANGED);
    }

    public function test_a_new_operator_assigned_to_a_line_makes_the_draft_stale(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 1);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(), $user);
        $this->assertNull($service->staleReason($draft->fresh()));

        User::factory()->create()->lines()->attach($line->id);

        $this->assertApplyRefusedFor($service, $draft, $user, self::STAFFING_CHANGED);
    }

    public function test_unassigning_an_operator_makes_the_draft_stale(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 0);
        $operator = User::factory()->create();
        $operator->lines()->attach($line->id);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(), $user);

        $operator->lines()->detach($line->id);

        $this->assertApplyRefusedFor($service, $draft, $user, self::STAFFING_CHANGED);
    }

    public function test_a_deactivated_worker_makes_the_draft_stale(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 2);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(), $user);

        $workers[0]->update(['is_active' => false]);

        $this->assertApplyRefusedFor($service, $draft, $user, self::STAFFING_CHANGED);
    }

    public function test_staffing_changes_are_ignored_when_the_draft_ignored_people(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(['respectWorkforce' => false]), $user);
        $this->assertNull($draft->fresh()->summary['staffing_fingerprint']);

        User::factory()->create()->lines()->attach($line->id);
        $this->absent($workers, '2026-10-12');

        $this->assertNull($service->staleReason($draft->fresh()));
        $this->assertSame(1, $service->apply($draft->fresh(), $user)['applied']);
    }

    public function test_a_non_staffing_change_without_workforce_reports_its_own_cause(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(['respectWorkforce' => false]), $user);

        $this->absent($workers, '2026-10-12');
        \App\Models\MaintenanceEvent::create([
            'title' => 'PM', 'event_type' => \App\Models\MaintenanceEvent::TYPE_PLANNED, 'status' => \App\Models\MaintenanceEvent::STATUS_PENDING,
            'line_id' => $line->id, 'scheduled_at' => $this->at('2026-10-13 06:00'), 'scheduled_end_at' => $this->at('2026-10-13 08:00'),
        ]);

        $this->assertSame('inputs', $service->staleReason($draft->fresh())['code']);
        $this->assertApplyRefusedFor($service, $draft, $user,
            'Planned work, shifts, maintenance or changeover rules changed after this schedule was generated. Generate it again.');
    }

    public function test_an_absence_outside_the_horizon_does_not_make_the_draft_stale(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        // The horizon runs 2026-10-12 06:00 to 2026-10-26 06:00.
        $draft = $service->generate($this->request(), $user);

        $this->absent($workers, '2026-10-11', '2026-10-27', '2026-11-30');

        $this->assertNull($service->staleReason($draft->fresh()));
        $this->assertSame(1, $service->apply($draft->fresh(), $user)['applied']);
    }

    public function test_an_absence_of_someone_who_staffs_no_line_does_not_make_the_draft_stale(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 1);
        // Logistics staff are never counted as operators, so their absence changes nothing.
        $unassigned = Worker::factory()->create(['workstation_id' => $line->workstations()->value('id'), 'is_logistics' => true]);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(), $user);

        $this->absent([$unassigned], '2026-10-12');

        $this->assertSame(1, $service->apply($draft->fresh(), $user)['applied']);
    }

    public function test_the_staffing_fingerprint_is_deterministic(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 2, people: 3);
        User::factory()->create()->lines()->attach($line->id);
        $this->absent([$workers[2], $workers[0]], '2026-10-13');
        $this->job($line, $type, 60);

        $first = $this->schedule()['staffing_fingerprint'];

        $this->assertIsString($first);
        $this->assertSame($first, $this->schedule()['staffing_fingerprint']);
        $this->absences['2026-10-13'] = array_reverse($this->absences['2026-10-13']);
        $this->assertSame($first, $this->schedule()['staffing_fingerprint']);
        $this->assertNull($this->schedule(['respectWorkforce' => false])['staffing_fingerprint']);
    }

    public function test_a_draft_saved_without_a_staffing_fingerprint_still_applies(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 1);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(), $user);
        $summary = $draft->summary;
        unset($summary['staffing_fingerprint']);
        $draft->update(['summary' => $summary]);

        User::factory()->create()->lines()->attach($line->id);

        $this->assertSame(1, $service->apply($draft->fresh(), $user)['applied']);
    }

    // ── Explaining a people shortfall ───────────────────────────────────────

    private function peopleReason(array $result, $order, string $code = 'workforce_capacity'): ?array
    {
        return collect($this->opsFor($result, $order)[0]['reasons'])->firstWhere('code', $code);
    }

    /** @return array<int, array<string, mixed>> line id => workforce entry */
    private function workforceOf(array $result): array
    {
        return collect($result['workforce'])->keyBy('line_id')->all();
    }

    public function test_workforce_reason_says_how_many_people_are_missing(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 2);
        $order = $this->job($line, $type, 60, ['required_operators' => 3]);

        $reason = $this->peopleReason($this->schedule(), $order);

        $this->assertSame(3, $reason['required']);
        $this->assertSame(2, $reason['available']);
        $this->assertSame(0, $reason['in_use']);
        $this->assertSame($line->id, $reason['line_id']);
        // Every window is one short; the earliest one is reported.
        $this->assertSame($this->at('2026-10-12 06:00')->toIso8601String(), $reason['window_start']);
        $this->assertSame($this->at('2026-10-12 07:00')->toIso8601String(), $reason['window_end']);
        $this->assertSame([], $reason['absent_worker_ids']);
        $this->assertSame('Needs 3 operators, 2 available.', $reason['message']);
    }

    public function test_workforce_reason_names_who_is_absent_in_the_window(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 3);
        $this->absent([$workers[0]], '2026-10-12');
        $order = $this->job($line, $type, 60, ['required_operators' => 3]);

        $reason = $this->peopleReason($this->schedule(['end' => $this->at('2026-10-13 00:00')]), $order);

        $this->assertSame(3, $reason['required']);
        $this->assertSame(2, $reason['available']);
        $this->assertSame([$workers[0]->id], $reason['absent_worker_ids']);
        $this->assertSame([['id' => $workers[0]->id, 'name' => $workers[0]->name]], $reason['absent_workers']);
        $this->assertSame("Needs 3 operators, 2 available (1 absent: {$workers[0]->name}).", $reason['message']);
    }

    public function test_workforce_reason_counts_people_held_by_other_work(): void
    {
        [$line, $type] = $this->cell(stations: 2, people: 2);
        // Takes one of the two people for the whole Monday shift.
        $this->job($line, $type, 480, attrs: ['priority' => 2]);
        $order = $this->job($line, $type, 60, ['required_operators' => 2], ['priority' => 1]);

        $reason = $this->peopleReason($this->schedule(['end' => $this->at('2026-10-13 00:00')]), $order);

        $this->assertSame(2, $reason['available']);
        $this->assertSame(1, $reason['in_use']);
        $this->assertSame('Needs 2 operators, 2 available. 1 busy with other work.', $reason['message']);
    }

    public function test_qualification_reason_names_the_required_skills(): void
    {
        $this->recordSkills();
        [$line, $type, [$qualified]] = $this->cell(stations: 1, people: 2);
        $skill = $this->line(['name' => 'Welding']); // stands in for a skill row
        $qualified->skills()->attach($skill->id);
        $order = $this->job($line, $type, 60, ['required_operators' => 2, 'required_skill_ids' => [$skill->id]]);

        $result = $this->schedule(['end' => $this->at('2026-10-13 00:00')]);
        $reason = $this->peopleReason($result, $order, 'qualification');

        $this->assertSame(ScheduledOperationStatus::InfeasibleQualification, $this->opStatus($result, $order));
        $this->assertSame(2, $reason['required']);
        $this->assertSame(1, $reason['available']);
        $this->assertSame([$skill->id], $reason['required_skill_ids']);
        $this->assertSame([['id' => $skill->id, 'name' => 'Welding']], $reason['required_skills']);
        $this->assertSame(['id' => $skill->id, 'name' => 'Welding'], $reason['bottleneck_skill']);
        $this->assertSame([['name' => $qualified->name]], $reason['skill_holders']);
        $this->assertSame("Needs 2 operators qualified in Welding, 1 available. Welding is held only by {$qualified->name}.", $reason['message']);
    }

    public function test_a_provider_that_cannot_explain_still_reports_the_numbers(): void
    {
        $this->fakeWorkforce(2);
        [$line, $type] = $this->cell(stations: 1, people: 0);
        $order = $this->job($line, $type, 60, ['required_operators' => 3]);

        $result = $this->schedule();
        $reason = $this->peopleReason($result, $order);

        $this->assertSame(3, $reason['required']);
        $this->assertSame(2, $reason['available']);
        $this->assertSame([], $reason['absent_worker_ids']);
        $this->assertSame(['tracked' => true, 'assigned' => null], array_intersect_key($this->workforceOf($result)[$line->id], ['tracked' => 0, 'assigned' => 0]));
    }

    // ── Which lines are staffing-tracked ────────────────────────────────────

    public function test_result_says_which_lines_are_not_staffing_tracked(): void
    {
        [$staffed, $type] = $this->cell(stations: 1, people: 1);
        User::factory()->create()->lines()->attach($staffed->id);
        $untracked = $this->line();
        $this->station($untracked, $type);
        $this->job($staffed, $type, 60);
        $loose = $this->job($untracked, $type, 60, ['required_operators' => 3]);

        $result = $this->schedule();
        $workforce = $this->workforceOf($result);

        $this->assertSame(['line_id' => $staffed->id, 'line_name' => $staffed->name, 'tracked' => true, 'assigned' => 2, 'respected' => true, 'single_holder_skills' => []], $workforce[$staffed->id]);
        $this->assertSame(['line_id' => $untracked->id, 'line_name' => $untracked->name, 'tracked' => false, 'assigned' => null, 'respected' => true, 'single_holder_skills' => []], $workforce[$untracked->id]);
        // Nobody is assigned, so three operators are not checked: machine capacity only.
        $this->assertSame(ScheduledOperationStatus::Scheduled, $this->opStatus($result, $loose));
    }

    public function test_ignoring_people_adds_no_people_reasons(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 2);
        $order = $this->job($line, $type, 60, ['required_operators' => 3]);

        $result = $this->schedule(['respectWorkforce' => false]);

        $this->assertSame(ScheduledOperationStatus::Scheduled, $this->opStatus($result, $order));
        $codes = array_column($this->opsFor($result, $order)[0]['reasons'], 'code');
        $this->assertNotContains('workforce_capacity', $codes);
        $this->assertNotContains('qualification', $codes);
        $this->assertTrue($this->workforceOf($result)[$line->id]['tracked']);
        $this->assertFalse($this->workforceOf($result)[$line->id]['respected']);
    }

    public function test_the_draft_keeps_the_staffing_section_and_the_numbers(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 2);
        $untracked = $this->line();
        $order = $this->job($line, $type, 60, ['required_operators' => 3]);

        $draft = app(ScheduleSimulationService::class)->generate($this->request(), User::factory()->create());

        $workforce = collect($draft->fresh()->summary['workforce'])->keyBy('line_id');
        $this->assertTrue($workforce[$line->id]['tracked']);
        $this->assertSame(2, $workforce[$line->id]['assigned']);
        $this->assertFalse($workforce[$untracked->id]['tracked']);

        $reasons = $draft->operations()->where('work_order_id', $order->id)->sole()->reasons;
        $this->assertSame(3, $reasons[0]['required']);
        $this->assertSame(2, $reasons[0]['available']);
    }

    /** Stand in for the workforce module's Worker::skills() relation. */
    private function recordSkills(): void
    {
        Schema::create('test_worker_skill', function ($table) {
            $table->foreignId('worker_id');
            $table->foreignId('skill_id');
        });
        Worker::resolveRelationUsing('skills', fn (Worker $w) => $w->belongsToMany(Line::class, 'test_worker_skill', 'worker_id', 'skill_id'));
        $this->beforeApplicationDestroyed(function () {
            $property = new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class, 'relationResolvers');
            $resolvers = $property->getValue();
            unset($resolvers[Worker::class]['skills']);
            $property->setValue(null, $resolvers);
        });
    }
}
