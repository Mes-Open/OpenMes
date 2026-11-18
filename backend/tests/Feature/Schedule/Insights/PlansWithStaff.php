<?php

namespace Tests\Feature\Schedule\Insights;

use App\Extension\Contracts\Null\NullWorkforceProvider;
use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Services\Schedule\Finite\AssignedStaffCapacityProvider;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * A small plant for planner insight tests.
 *
 * Week of Monday 2026-11-23, two weekday shifts (Morning 06–14, Afternoon
 * 14–22). People are operator accounts assigned to lines (Admin → Lines →
 * Operators), each linked to a worker so a workforce test double can report
 * them absent or skilled.
 */
trait PlansWithStaff
{
    /** @var array<string, list<int>> date => absent worker ids */
    public array $absences = [];

    /** @var array<int, list<int>>|null worker id => skill ids; null = skills not recorded */
    public ?array $skills = null;

    /** @var array<int, string> */
    public array $skillNames = [];

    protected function setUpPlant(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 05:00:00'));
        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        Shift::create(['name' => 'Morning', 'code' => 'M', 'start_time' => '06:00', 'end_time' => '14:00', 'days_of_week' => [1, 2, 3, 4, 5], 'is_active' => true, 'sort_order' => 1]);
        Shift::create(['name' => 'Afternoon', 'code' => 'A', 'start_time' => '14:00', 'end_time' => '22:00', 'days_of_week' => [1, 2, 3, 4, 5], 'is_active' => true, 'sort_order' => 2]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Absences come from a test double of the workforce contract. */
    protected function withAbsenceProvider(): void
    {
        $test = $this;
        $this->app->bind(WorkforceProvider::class, fn () => new class($test) extends NullWorkforceProvider
        {
            public function __construct(private $test) {}

            public function absentWorkerIds(CarbonInterface $date): array
            {
                return $this->test->absences[$date->toDateString()] ?? [];
            }
        });
    }

    /** Skills come from a test double of the capacity contract (core records none). */
    protected function withSkillProvider(array $skillNames): void
    {
        $this->skills = [];
        $this->skillNames = $skillNames;
        $test = $this;
        $this->app->bind(WorkforceCapacityProvider::class, fn ($app) => new class($app->make(WorkforceProvider::class), $test) extends AssignedStaffCapacityProvider
        {
            public function __construct(WorkforceProvider $people, private $test)
            {
                parent::__construct($people);
            }

            public function skillHolders(?int $lineId): ?array
            {
                $holders = [];
                foreach ($this->assignedOperatorKeys()[$lineId] ?? [] as $key) {
                    foreach ($this->test->skills[(int) substr($key, 2)] ?? [] as $skill) {
                        $holders[$skill][] = $key;
                    }
                }
                ksort($holders);

                return $holders;
            }

            public function skillNames(array $ids): array
            {
                return array_intersect_key($this->test->skillNames, array_flip($ids));
            }
        });
    }

    /** A provider that only counts: no names, no absences, no roster. */
    protected function withCountOnlyProvider(int $operators): void
    {
        $this->app->bind(WorkforceCapacityProvider::class, fn () => new class($operators) implements WorkforceCapacityProvider
        {
            public function __construct(private int $operators) {}

            public function availableOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?int
            {
                return $this->operators;
            }

            public function qualifiedOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?int
            {
                return $this->operators;
            }
        });
    }

    protected function plantLine(string $name, string $code): Line
    {
        return Line::factory()->create(['name' => $name, 'code' => $code, 'is_active' => true]);
    }

    /** An Operator account (with a worker record) assigned to the lines. */
    protected function operator(string $name, Line ...$lines): User
    {
        $worker = Worker::factory()->create(['name' => $name, 'workstation_id' => null]);
        $user = User::factory()->create(['name' => $name, 'worker_id' => $worker->id]);
        $user->assignRole('Operator');
        $user->lines()->attach(array_map(fn (Line $l) => $l->id, $lines));

        return $user;
    }

    protected function planner(string $role = 'Admin'): User
    {
        $user = User::factory()->create(['name' => $role.' User']);
        $user->assignRole($role);
        if ($role !== 'Admin') {
            $user->givePermissionTo(Permission::findOrCreate('tab:schedule', 'web'));
        }

        return $user;
    }

    /**
     * An order dropped on a weekly cell: line, day and shift, like the board does.
     *
     * @param  list<int>  $skills
     */
    protected function planned(Line $line, string $date, int $shift, int $operators = 1, array $attrs = [], array $skills = []): WorkOrder
    {
        $start = ['1' => '06:00', '2' => '14:00'][(string) $shift];

        return WorkOrder::factory()->create($attrs + [
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_qty' => 10,
            'due_date' => '2026-11-27',
            'planned_start_at' => "{$date} {$start}:00",
            'planned_end_at' => Carbon::parse($date)->addDay()->format('Y-m-d 00:00:00'),
            'shift_number' => $shift,
            'end_date' => $date,
            'end_shift_number' => $shift,
            'week_number' => null,
            'process_snapshot' => ['steps' => [[
                'step_number' => 10, 'name' => 'Turn', 'estimated_duration_minutes' => 240,
                'required_operators' => $operators, 'required_skill_ids' => $skills,
            ]], 'bom' => []],
        ]);
    }

    protected function absent(User $user, string $date): void
    {
        $this->absences[$date][] = $user->worker_id;
    }

    protected function board(array $query = []): array
    {
        return $query + ['view_mode' => 'weekly', 'start_date' => '2026-11-23'];
    }

    protected function drawer(User $user, array $query = []): array
    {
        return $this->actingAs($user)->getJson('/admin/schedule/insights?'.http_build_query($this->board($query)))
            ->assertOk()->json();
    }

    protected function insight(array $state, string $type): ?array
    {
        return collect($state['drawer']['insights'])->firstWhere('type', $type);
    }

    protected function cellOf(array $state, Line $line, string $date, int $shift): ?array
    {
        return collect($state['staffing']['cells'])->first(fn ($c) => $c['line_id'] === $line->id && $c['date'] === $date && $c['shift'] === $shift);
    }
}
