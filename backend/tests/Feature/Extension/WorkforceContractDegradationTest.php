<?php

namespace Tests\Feature\Extension;

use App\Extension\Contracts\Null\NullWorkforceProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\User;
use App\Models\Worker;
use App\Services\Production\ProductionCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RequiresNoModules;
use Tests\TestCase;

/**
 * What the application does when nothing records workforce administration.
 *
 * Absences, crew break windows and wage groups are optional: an installation may
 * never record any of them. Scheduling and costing ask through WorkforceProvider
 * so they get an answer either way.
 *
 * The claim these tests defend is narrow and load-bearing: binding the null
 * implementation must produce the SAME behaviour as the real one reading empty
 * tables. If that ever stops being true, every caller silently changes meaning
 * on installations that never recorded this data.
 */
class WorkforceContractDegradationTest extends TestCase
{
    use RefreshDatabase;
    use RequiresNoModules;

    public function test_core_answers_with_the_null_provider(): void
    {
        $this->skipIfAnyModuleIsInstalled();

        // The implementation that reads workforce tables ships with a module, so
        // core's default is the null one. An installation without the module
        // still gets an answer to every question — the neutral one.
        $this->assertInstanceOf(NullWorkforceProvider::class, app(WorkforceProvider::class));
    }

    public function test_costing_falls_back_to_the_configured_rate_without_wage_groups(): void
    {
        config(['openmmes.default_pay_rate' => 42.0, 'openmmes.default_pay_type' => 'hourly']);
        app()->bind(WorkforceProvider::class, NullWorkforceProvider::class);

        $service = app(ProductionCostService::class);
        $worker = Worker::factory()->create(['pay_rate' => null, 'wage_group_id' => null]);

        // Reached through the private method because that is the seam the
        // contract replaced; the public report paths all funnel through it.
        $rate = (fn () => $this->payRateOf($worker, 'hourly'))->call($service);

        $this->assertSame(42.0, $rate);
    }

    public function test_a_workers_own_rate_still_wins_without_wage_groups(): void
    {
        config(['openmmes.default_pay_rate' => 42.0]);
        app()->bind(WorkforceProvider::class, NullWorkforceProvider::class);

        $service = app(ProductionCostService::class);
        $worker = Worker::factory()->create(['pay_rate' => 99.5]);

        $rate = (fn () => $this->payRateOf($worker, 'hourly'))->call($service);

        $this->assertSame(99.5, $rate);
    }

    public function test_a_module_can_replace_the_provider(): void
    {
        // The other direction: an installation that records more than core does
        // rebinds the contract and every caller picks it up.
        app()->bind(WorkforceProvider::class, fn () => new class extends NullWorkforceProvider
        {
            public function hourlyRate(Worker $worker): ?float
            {
                return 7.25;
            }
        });

        $service = app(ProductionCostService::class);
        $worker = Worker::factory()->create(['pay_rate' => null]);

        $rate = (fn () => $this->payRateOf($worker, 'hourly'))->call($service);

        $this->assertSame(7.25, $rate);
    }

    public function test_the_user_form_simply_offers_no_workforce_pickers(): void
    {
        // /admin/users stays in core while crews, wage groups and skills ship as
        // a module. Without them the page must still open — with the pickers
        // empty — rather than failing on tables that were never created.
        app()->bind(WorkforceProvider::class, NullWorkforceProvider::class);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->get('/admin/users/create');

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame([], $props['crews']);
        $this->assertSame([], $props['wageGroups']);
        $this->assertSame([], $props['skills']);
    }

    public function test_a_crew_cannot_be_submitted_when_none_are_offered(): void
    {
        // The rules are built from what the contract offered, not from
        // `exists:crews,id` — so a hand-written request cannot set a crew the
        // form never showed, and the rule does not query a missing table.
        app()->bind(WorkforceProvider::class, NullWorkforceProvider::class);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Someone',
                'username' => 'someone',
                'email' => 'someone@example.test',
                'password' => 'Password1234!',
                'password_confirmation' => 'Password1234!',
                'account_type' => 'user',
                'roles' => ['Operator'],
                'worker_code' => 'W-1',
                'worker_crew_id' => 999,
            ])
            ->assertSessionHasErrors('worker_crew_id');
    }

    public function test_the_planner_still_shows_unstaffed_demand_without_crews(): void
    {
        // The crew axis is a module feature, but demand on a line nobody staffs
        // is not — it is what the Unassigned row exists to show, and it has to
        // survive the crews going away.
        app()->bind(WorkforceProvider::class, NullWorkforceProvider::class);

        $grid = app(\App\Services\Schedule\CapacityService::class)
            ->crewCapacity(now()->startOfWeek(), now()->endOfWeek(), 'week');

        $this->assertArrayHasKey('resources', $grid);
        $this->assertArrayHasKey('buckets', $grid);
    }
}
