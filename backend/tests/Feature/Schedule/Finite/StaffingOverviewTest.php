<?php

namespace Tests\Feature\Schedule\Finite;

use App\Enums\ScheduledOperationStatus;
use App\Extension\Contracts\Null\NullWorkforceProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\Line;
use App\Models\Shift;
use App\Models\Worker;
use App\Models\WorkstationType;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The planner's staffing view of a run: the "Delayed by staffing" note on
 * steps people pushed past their machine slot, the per-line, per-shift
 * staffing rows and the absences in the horizon with the orders they hit.
 *
 * Calendar: Monday 2026-10-12, one global 06:00–14:00 weekday shift.
 */
class StaffingOverviewTest extends TestCase
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
            public function __construct(private StaffingOverviewTest $test) {}

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

    private function absent(Worker $worker, string ...$dates): void
    {
        foreach ($dates as $date) {
            $this->absences[$date][] = $worker->id;
        }
    }

    /** A line with $stations interchangeable machines and $people named workers. */
    private function cell(int $stations, int $people, string $name = 'Line A'): array
    {
        $line = $this->line(['name' => $name]);
        $type = WorkstationType::factory()->create();
        $first = null;
        for ($i = 0; $i < $stations; $i++) {
            $station = $this->station($line, $type);
            $first ??= $station;
        }
        $workers = [];
        for ($i = 1; $i <= $people; $i++) {
            $workers[] = Worker::factory()->create(['workstation_id' => $first->id, 'name' => "Worker {$i}"]);
        }

        return [$line, $type, $workers];
    }

    private function job(Line $line, WorkstationType $type, int $minutes, array $step = [], array $attrs = [])
    {
        return $this->order($line, [['estimated_duration_minutes' => $minutes, 'workstation_type_id' => $type->id] + $step], $attrs);
    }

    private function reason(array $op, string $code): ?array
    {
        return collect($op['reasons'])->firstWhere('code', $code);
    }

    /** @return list<array<string, mixed>> */
    private function rows(array $result, Line $line, string $date): array
    {
        return array_values(array_filter($result['staffing']['shifts'], fn ($r) => $r['line_id'] === $line->id && $r['date'] === $date));
    }

    /** A 3-person step on a line of 3 with one of them away on Monday. */
    private function oneAbsentOnMonday(array $attrs = []): array
    {
        [$line, $type, $workers] = $this->cell(stations: 1, people: 3);
        $this->absent($workers[0], '2026-10-12');
        $order = $this->job($line, $type, 120, ['required_operators' => 3], $attrs);

        return [$line, $workers, $order];
    }

    // ── Delayed by staffing ─────────────────────────────────────────────────

    public function test_delayed_by_staffing_note_records_machine_start_and_delay(): void
    {
        [$line, $workers, $order] = $this->oneAbsentOnMonday();

        $op = $this->opsFor($this->schedule(), $order)[0];

        $this->assertSame(ScheduledOperationStatus::Scheduled, $op['status']);
        $this->assertEquals($this->at('2026-10-13 06:00'), $op['planned_start_at']);
        $note = end($op['reasons']);
        $this->assertSame('delayed_by_staffing', $note['code']);
        $this->assertSame($this->at('2026-10-12 06:00')->toIso8601String(), $note['machine_start']);
        $this->assertSame($this->at('2026-10-13 06:00')->toIso8601String(), $note['planned_start']);
        $this->assertSame(1440, $note['delay_minutes']);
        $this->assertSame($line->id, $note['line_id']);
        $this->assertSame(3, $note['required']);
        $this->assertSame(2, $note['available']);
        $this->assertSame([$workers[0]->id], $note['absent_worker_ids']);
        $this->assertSame('Worker 1', $note['absent_workers'][0]['name']);
        $this->assertSame('workforce_capacity', $note['cause']['code']);
        $this->assertSame(
            'Delayed by staffing: 2026-10-12 06:00 → 2026-10-13 06:00 — Needs 3 operators, 2 available (1 absent: Worker 1).',
            $note['message'],
        );
    }

    public function test_late_status_is_kept_with_the_note(): void
    {
        [, , $order] = $this->oneAbsentOnMonday(['due_date' => '2026-10-12']);

        $op = $this->opsFor($this->schedule(), $order)[0];

        $this->assertSame(ScheduledOperationStatus::ScheduledLate, $op['status']);
        $this->assertNotNull($this->reason($op, 'late'));
        $this->assertNotNull($this->reason($op, 'delayed_by_staffing'));
    }

    public function test_no_note_when_machines_caused_the_shift(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 5);
        $a = $this->job($line, $type, 240, attrs: ['priority' => 2]);
        $b = $this->job($line, $type, 60, attrs: ['priority' => 1]);

        $result = $this->schedule();

        $op = $this->opsFor($result, $b)[0];
        $this->assertEquals($this->at('2026-10-12 10:00'), $op['planned_start_at']);
        $this->assertNull($this->reason($op, 'delayed_by_staffing'));
        $this->assertNull($this->reason($this->opsFor($result, $a)[0], 'delayed_by_staffing'));
    }

    public function test_no_note_when_people_are_ignored(): void
    {
        [, , $order] = $this->oneAbsentOnMonday();

        $op = $this->opsFor($this->schedule(['respectWorkforce' => false]), $order)[0];

        $this->assertEquals($this->at('2026-10-12 06:00'), $op['planned_start_at']);
        $this->assertNull($this->reason($op, 'delayed_by_staffing'));
    }

    // ── Staffing rows ───────────────────────────────────────────────────────

    public function test_staffing_rows_show_assigned_absent_demand_and_shortfall(): void
    {
        [$line, $workers] = $this->oneAbsentOnMonday();

        $result = $this->schedule();

        $monday = $this->rows($result, $line, '2026-10-12');
        $this->assertCount(1, $monday);
        $this->assertTrue($monday[0]['tracked']);
        $this->assertSame('Day', $monday[0]['shift_name']);
        $this->assertSame(3, $monday[0]['assigned']);
        $this->assertSame(2, $monday[0]['present']);
        $this->assertSame([['id' => $workers[0]->id, 'name' => 'Worker 1']], $monday[0]['absent']);
        $this->assertSame(1, $monday[0]['shortfall']);
        $this->assertSame(3, $monday[0]['needed']);
        $this->assertEquals(0, $monday[0]['person_hours']);

        $tuesday = $this->rows($result, $line, '2026-10-13');
        $this->assertCount(1, $tuesday);
        $this->assertSame(3, $tuesday[0]['peak_operators']);
        $this->assertEquals(6.0, $tuesday[0]['person_hours']); // 2 h × 3 people
        $this->assertSame(3, $tuesday[0]['present']);
        $this->assertSame([], $tuesday[0]['absent']);
        $this->assertSame(0, $tuesday[0]['shortfall']);

        // A quiet day keeps its cell: three there, nothing asked of them.
        $wednesday = $this->rows($result, $line, '2026-10-14');
        $this->assertCount(1, $wednesday);
        $this->assertSame(3, $wednesday[0]['available']);
        $this->assertSame(0, $wednesday[0]['needed']);
        $this->assertSame(0, $wednesday[0]['shortfall']);
    }

    public function test_several_shifts_give_one_row_per_shift_run(): void
    {
        Shift::create(['name' => 'Evening', 'code' => 'E', 'start_time' => '14:00', 'end_time' => '22:00', 'days_of_week' => [1, 2, 3, 4, 5], 'is_active' => true]);
        [$line, $type, $workers] = $this->cell(stations: 1, people: 2);
        $this->absent($workers[1], '2026-10-12');

        $rows = $this->rows($this->schedule(), $line, '2026-10-12');

        $this->assertSame(['Day', 'Evening'], array_column($rows, 'shift_name'));
        $this->assertSame([1, 1], array_column($rows, 'present'));
    }

    public function test_untracked_line_row_is_flagged(): void
    {
        $line = $this->line(['name' => 'Line B']);
        $this->order($line, [['estimated_duration_minutes' => 60, 'required_operators' => 2]]);

        $row = $this->rows($this->schedule(), $line, '2026-10-12')[0];

        $this->assertFalse($row['tracked']);
        $this->assertNull($row['assigned']);
        $this->assertNull($row['present']);
        $this->assertNull($row['shortfall']);
        $this->assertSame([], $row['absent']);
        $this->assertEquals(2.0, $row['person_hours']);
        $this->assertSame(2, $row['peak_operators']);
    }

    public function test_respect_workforce_off_still_reports_shortfall(): void
    {
        [$line] = $this->oneAbsentOnMonday();

        $monday = $this->rows($this->schedule(['respectWorkforce' => false]), $line, '2026-10-12')[0];

        $this->assertSame(2, $monday['present']);
        $this->assertSame(3, $monday['peak_operators']);
        $this->assertSame(1, $monday['shortfall']);
    }

    public function test_a_blocked_step_counts_against_the_shift_it_could_not_staff(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 2);
        $this->job($line, $type, 60, ['required_operators' => 3]);

        $result = $this->schedule(['end' => $this->monday->copy()->addDay()]);

        $row = $this->rows($result, $line, '2026-10-12')[0];
        $this->assertSame(1, $row['shortfall']);
        $this->assertSame(1, $row['blocked_operations']);
    }

    // ── Absences ────────────────────────────────────────────────────────────

    public function test_absences_list_names_affected_orders(): void
    {
        [$line, $workers, $order] = $this->oneAbsentOnMonday();
        // Away on a day nothing needs them.
        $this->absent($workers[1], '2026-10-15');

        $absences = $this->schedule()['staffing']['absences'];

        $this->assertCount(2, $absences);
        $this->assertSame($workers[0]->id, $absences[0]['worker_id']);
        $this->assertSame('Worker 1', $absences[0]['name']);
        $this->assertSame(['2026-10-12'], $absences[0]['dates']);
        $this->assertSame([['id' => $line->id, 'name' => 'Line A']], $absences[0]['lines']);
        $this->assertSame([['work_order_id' => $order->id, 'order_no' => $order->order_no]], $absences[0]['affected_orders']);

        $this->assertSame($workers[1]->id, $absences[1]['worker_id']);
        $this->assertSame(['2026-10-15'], $absences[1]['dates']);
        $this->assertSame([], $absences[1]['affected_orders']);
    }

    public function test_the_draft_summary_keeps_the_staffing_breakdown(): void
    {
        [$line] = $this->oneAbsentOnMonday();
        $user = \App\Models\User::factory()->create();

        $draft = app(\App\Services\Schedule\Finite\ScheduleSimulationService::class)->generate($this->request(), $user);

        $staffing = $draft->fresh()->summary['staffing'];
        $this->assertSame($line->id, $staffing['shifts'][0]['line_id']);
        $this->assertSame(1, $staffing['shifts'][0]['shortfall']);
        $this->assertCount(1, $staffing['absences']);
    }

    // ── One person on two lines ─────────────────────────────────────────────

    /** Line A and Line B, one machine each, staffed by one operator account assigned to both. */
    private function sharedPerson(): array
    {
        [$lineA, $typeA] = $this->cell(stations: 1, people: 0, name: 'Line A');
        [$lineB, $typeB] = $this->cell(stations: 1, people: 0, name: 'Line B');
        $piotr = \App\Models\User::factory()->create(['name' => 'Piotr Wójcik']);
        $piotr->lines()->attach([$lineA->id, $lineB->id]);
        $this->job($lineA, $typeA, 60, attrs: ['priority' => 2]);
        $this->job($lineB, $typeB, 60, attrs: ['priority' => 1]);

        return [$lineA, $lineB];
    }

    public function test_a_shared_operator_is_counted_once_across_lines(): void
    {
        [$lineA, $lineB] = $this->sharedPerson();

        // People ignored: both steps land at 06:00, and one person cannot be on both.
        $result = $this->schedule(['respectWorkforce' => false]);

        foreach ([[$lineA, $lineB], [$lineB, $lineA]] as [$line, $other]) {
            $row = $this->rows($result, $line, '2026-10-12')[0];
            $this->assertSame(1, $row['present'], 'Piotr is on the line\'s own roster');
            $this->assertSame(0, $row['available'], 'but the other line\'s step holds him');
            $this->assertSame(1, $row['needed']);
            $this->assertSame(1, $row['shortfall']);
            $this->assertSame([['line_id' => $other->id, 'line_name' => $other->name, 'names' => ['Piotr Wójcik']]], $row['held_elsewhere']);
        }
    }

    public function test_a_shared_operator_booked_one_after_the_other_is_exactly_enough(): void
    {
        [$lineA, $lineB] = $this->sharedPerson();

        $result = $this->schedule();

        // The scheduler runs B after A; each line has him while its step runs.
        foreach ([$lineA, $lineB] as $line) {
            $row = $this->rows($result, $line, '2026-10-12')[0];
            $this->assertSame(1, $row['available']);
            $this->assertSame(1, $row['needed']);
            $this->assertSame(0, $row['shortfall']);
            $this->assertSame([], $row['held_elsewhere']);
        }
    }

    // ── Steps behind a step short of people ─────────────────────────────────

    public function test_a_step_behind_one_short_of_people_says_which_and_by_how_much(): void
    {
        [$line, $type] = $this->cell(stations: 1, people: 2);
        $order = $this->order($line, [
            ['estimated_duration_minutes' => 60, 'workstation_type_id' => $type->id, 'required_operators' => 3],
            ['estimated_duration_minutes' => 60, 'workstation_type_id' => $type->id],
        ]);

        $ops = $this->opsFor($this->schedule(), $order);

        $this->assertSame(ScheduledOperationStatus::InfeasibleWorkforceCapacity, $ops[0]['status']);
        $this->assertSame(ScheduledOperationStatus::InfeasiblePredecessor, $ops[1]['status']);
        $reason = $ops[1]['reasons'][0];
        $this->assertSame('predecessor_not_schedulable', $reason['code']);
        $this->assertSame(10, $reason['blocking_step_number']);
        $this->assertSame(3, $reason['required']);
        $this->assertSame(2, $reason['available']);
        $this->assertSame('workforce_capacity', $reason['cause']['code']);
        $this->assertSame('Waits for step 10, which is short of operators (needs 3, 2 available).', $reason['message']);
    }

    public function test_a_step_behind_one_blocked_by_machines_names_the_step_only(): void
    {
        $line = $this->line();
        $order = $this->order($line, [
            ['estimated_duration_minutes' => null],
            ['estimated_duration_minutes' => 60],
        ]);

        $reason = $this->opsFor($this->schedule(), $order)[1]['reasons'][0];

        $this->assertSame('Waits for step 10, which could not be scheduled.', $reason['message']);
        $this->assertArrayNotHasKey('cause', $reason);
    }
}
