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
    }

    public function test_probe_a_multi_skilled_operator_is_not_booked_for_two_skilled_steps_at_once(): void
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
    }

    public function test_probe_night_shift_started_before_an_absence_day_is_not_blocked(): void
    {
        Shift::query()->update(['is_active' => false]);
        Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '22:00', 'end_time' => '06:00', 'days_of_week' => [1, 2, 3, 4, 5], 'is_active' => true]);
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        // Away on Tuesday — i.e. Tuesday's night shift, not Monday's.
        $this->absent($workers, '2026-10-13');
        $order = $this->job($line, $type, 480);

        $this->assertEquals($this->at('2026-10-12 22:00'), $this->start($this->schedule(), $order));
    }

    public function test_probe_workforce_reason_says_how_many_people_are_missing(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 2);
        $order = $this->job($line, $type, 60, ['required_operators' => 3]);

        $reason = collect($this->opsFor($this->schedule(), $order)[0]['reasons'])->firstWhere('code', 'workforce_capacity');

        $this->assertArrayHasKey('required', $reason, 'material reasons carry shortages; people reasons carry nothing');
        $this->assertArrayHasKey('available', $reason);
    }

    public function test_probe_an_absence_recorded_after_the_draft_makes_it_stale(): void
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 1);
        $this->job($line, $type, 60);
        $user = User::factory()->create();
        $service = app(ScheduleSimulationService::class);
        $draft = $service->generate($this->request(), $user);

        // The only operator calls in sick for Monday after the plan was drafted.
        $this->absent($workers, '2026-10-12');

        $this->expectException(\DomainException::class);
        $service->apply($draft->fresh(), $user);
    }

    public function test_probe_result_says_which_lines_are_not_staffing_tracked(): void
    {
        [$staffed, $type] = $this->cell(stations: 1, people: 1);
        $untracked = $this->line();
        $this->station($untracked, $type);
        $this->job($staffed, $type, 60);
        $this->job($untracked, $type, 60);

        $result = $this->schedule();

        $this->assertArrayHasKey('workforce', $result, 'planner cannot tell a staffed line from an unconstrained one');
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
