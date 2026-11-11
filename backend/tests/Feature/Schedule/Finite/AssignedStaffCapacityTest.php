<?php

namespace Tests\Feature\Schedule\Finite;

use App\Extension\Contracts\Null\NullWorkforceProvider;
use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkstationType;
use App\Services\Schedule\Finite\AssignedStaffCapacityProvider;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Core's people count for finite scheduling: the operator accounts and workers
 * assigned to a line, less those the workforce provider reports absent. A line
 * nobody is assigned to is not tracked and never constrains.
 */
class AssignedStaffCapacityTest extends TestCase
{
    use RefreshDatabase;
    use SchedulesFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchedule();
    }

    /** @param  list<int>  $absentIds  workers away on 2026-10-13 */
    private function absentOnTuesday(array $absentIds): void
    {
        $this->app->instance(WorkforceProvider::class, new class($absentIds) extends NullWorkforceProvider
        {
            public function __construct(private array $absentIds) {}

            public function absentWorkerIds(CarbonInterface $date): array
            {
                return $date->toDateString() === '2026-10-13' ? $this->absentIds : [];
            }
        });
    }

    private function staff(Line $line, int $count): array
    {
        $station = $this->station($line);

        return Worker::factory()->count($count)->create(['workstation_id' => $station->id])->all();
    }

    private function provider(): WorkforceCapacityProvider
    {
        return app(WorkforceCapacityProvider::class);
    }

    public function test_core_binds_the_assigned_workers_provider(): void
    {
        $this->assertInstanceOf(AssignedStaffCapacityProvider::class, $this->provider());
    }

    public function test_counts_active_production_workers_assigned_to_the_line(): void
    {
        $line = $this->line();
        $station = $this->station($line);
        $this->staff($line, 2);
        Worker::factory()->inactive()->create(['workstation_id' => $station->id]);
        Worker::factory()->logistics()->create(['workstation_id' => $station->id]);

        $this->assertSame(2, $this->provider()->availableOperators($line->id, $this->at('2026-10-12 06:00'), $this->at('2026-10-12 14:00')));
    }

    public function test_operator_accounts_assigned_to_the_line_count_once_with_their_worker(): void
    {
        $line = $this->line();
        [$worker] = $this->staff($line, 1);
        $linked = User::factory()->create(['worker_id' => $worker->id]);
        $plain = User::factory()->create();
        $linked->lines()->attach($line->id);
        $plain->lines()->attach($line->id);

        // The linked account is the same person as the worker; the other is new.
        $this->assertSame(2, $this->provider()->availableOperators($line->id, $this->at('2026-10-12 06:00'), $this->at('2026-10-12 14:00')));
    }

    public function test_absent_workers_are_not_available_on_their_day(): void
    {
        $line = $this->line();
        [$first] = $this->staff($line, 2);
        $this->absentOnTuesday([$first->id]);
        $provider = $this->provider();

        $this->assertSame(2, $provider->availableOperators($line->id, $this->at('2026-10-12 06:00'), $this->at('2026-10-12 14:00')));
        $this->assertSame(1, $provider->availableOperators($line->id, $this->at('2026-10-13 06:00'), $this->at('2026-10-13 14:00')));
        // A window reaching into Tuesday's shift counts the worker as away for all of it.
        $this->assertSame(1, $provider->availableOperators($line->id, $this->at('2026-10-12 12:00'), $this->at('2026-10-13 08:00')));
    }

    public function test_a_night_shift_belongs_to_the_date_it_starts(): void
    {
        Shift::query()->update(['is_active' => false]);
        Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '22:00', 'end_time' => '06:00', 'days_of_week' => [1, 2, 3, 4, 5], 'is_active' => true]);
        $line = $this->line();
        [$first] = $this->staff($line, 2);
        $this->absentOnTuesday([$first->id]);
        $provider = $this->provider();
        $mondayNight = [$this->at('2026-10-12 22:00'), $this->at('2026-10-13 06:00')];
        $tuesdayNight = [$this->at('2026-10-13 22:00'), $this->at('2026-10-14 06:00')];

        // Away on Tuesday: Monday's night (ending Tuesday morning) is untouched…
        $this->assertSame(2, $provider->availableOperators($line->id, ...$mondayNight));
        $this->assertSame([], $provider->absentWorkerIds($line->id, ...$mondayNight));
        // …and Tuesday's night, though it ends on Wednesday, is not.
        $this->assertSame(1, $provider->availableOperators($line->id, ...$tuesdayNight));
        $this->assertSame([$first->id], $provider->absentWorkerIds($line->id, ...$tuesdayNight));
        // The early hours of Wednesday still belong to Tuesday's night.
        $this->assertSame(1, $provider->availableOperators($line->id, $this->at('2026-10-14 02:00'), $this->at('2026-10-14 03:00')));
    }

    public function test_a_window_outside_every_shift_uses_its_calendar_date(): void
    {
        $line = $this->line();
        [$first] = $this->staff($line, 2);
        $this->absentOnTuesday([$first->id]);
        $provider = $this->provider();

        // Tuesday 18:00–20:00 lies outside the 06:00–14:00 day shift.
        $this->assertSame(1, $provider->availableOperators($line->id, $this->at('2026-10-13 18:00'), $this->at('2026-10-13 20:00')));
        $this->assertSame(2, $provider->availableOperators($line->id, $this->at('2026-10-14 18:00'), $this->at('2026-10-14 20:00')));
    }

    public function test_a_line_nobody_is_assigned_to_is_not_tracked(): void
    {
        $staffed = $this->line();
        $this->staff($staffed, 1);
        $empty = $this->line();

        $this->assertNull($this->provider()->availableOperators($empty->id, $this->at('2026-10-12 06:00'), $this->at('2026-10-12 14:00')));
    }

    public function test_qualification_is_not_tracked_without_worker_skills(): void
    {
        $line = $this->line();
        $this->staff($line, 3);

        $this->assertNull($this->provider()->qualifiedOperators($line->id, $this->at('2026-10-12 06:00'), $this->at('2026-10-12 14:00'), [1]));
    }

    public function test_qualification_counts_workers_holding_every_skill_when_skills_are_recorded(): void
    {
        // Stand in for the workforce module: it adds Worker::skills() at boot.
        // Lines play the part of skill rows; only their ids matter here.
        \Illuminate\Support\Facades\Schema::create('test_worker_skill', function ($table) {
            $table->foreignId('worker_id');
            $table->foreignId('skill_id');
        });
        Worker::resolveRelationUsing('skills', fn (Worker $w) => $w->belongsToMany(Line::class, 'test_worker_skill', 'worker_id', 'skill_id'));
        $this->beforeApplicationDestroyed(fn () => $this->forgetSkillsRelation());

        $line = $this->line();
        [$welder, $both, $none] = $this->staff($line, 3);
        $skillA = $this->line();
        $skillB = $this->line();
        $welder->skills()->attach([$skillA->id]);
        $both->skills()->attach([$skillA->id, $skillB->id]);

        $count = fn (array $skills) => $this->provider()->qualifiedOperators($line->id, $this->at('2026-10-12 06:00'), $this->at('2026-10-12 14:00'), $skills);

        $this->assertSame(2, $count([$skillA->id]));
        $this->assertSame(1, $count([$skillA->id, $skillB->id]));
    }

    private function forgetSkillsRelation(): void
    {
        $property = new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class, 'relationResolvers');
        $resolvers = $property->getValue();
        unset($resolvers[Worker::class]['skills']);
        $property->setValue(null, $resolvers);
    }

    public function test_assigned_workers_constrain_the_schedule_unless_the_run_ignores_them(): void
    {
        $line = $this->line();
        $type = WorkstationType::factory()->create();
        $this->station($line, $type);
        $this->station($line, $type);
        $this->staff($line, 1);
        $first = $this->order($line, [['estimated_duration_minutes' => 60, 'workstation_type_id' => $type->id]], ['priority' => 5]);
        $second = $this->order($line, [['estimated_duration_minutes' => 60, 'workstation_type_id' => $type->id]]);

        // Two free machines, one person: the second job waits for the first.
        $result = $this->schedule();
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->opsFor($result, $first)[0]['planned_start_at']);
        $this->assertEquals($this->at('2026-10-12 07:00'), $this->opsFor($result, $second)[0]['planned_start_at']);

        // With people switched off for the run, both machines start at once.
        $result = app(\App\Services\Schedule\Finite\FiniteScheduler::class)->schedule(new \App\Services\Schedule\Finite\SchedulingRequest(
            start: $this->monday->copy(),
            end: $this->monday->copy()->addDays(14),
            respectWorkforce: false,
        ));
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->opsFor($result, $second)[0]['planned_start_at']);
    }

    public function test_generate_accepts_and_records_the_workforce_switch(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->order($this->line(), [['estimated_duration_minutes' => 60]]);

        $this->actingAs($admin)->post('/admin/schedule/simulations', ['horizon_days' => 7, 'respect_workforce' => false])
            ->assertRedirect('/admin/schedule/auto');
        $this->assertFalse(\App\Models\ScheduleSimulation::latest('id')->first()->parameters['respect_workforce']);
    }
}
