<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Workers screen on an installation with no workforce module.
 *
 * A community plant still hires people, so the worker record itself is core.
 * What the optional module adds around it — crews, wage groups, personnel
 * classes, certifications — is simply absent, and the page has to open anyway
 * rather than fail on tables that were never created.
 */
class WorkerScreenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_the_list_opens_without_the_workforce_module(): void
    {
        Worker::factory()->create(['code' => 'W-1', 'name' => 'Anna Kowalska']);

        $this->actingAs($this->admin())
            ->get(route('admin.workers.index'))
            ->assertOk();
    }

    public function test_the_form_offers_no_crew_or_wage_group(): void
    {
        // The pickers come from the WorkforceProvider contract, which answers
        // with empty lists when nothing records that data.
        $response = $this->actingAs($this->admin())->get(route('admin.workers.create'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame([], $props['crews']);
        $this->assertSame([], $props['wageGroups']);
        $this->assertSame([], $props['personnelClasses']);
    }

    public function test_a_worker_can_be_created_with_nothing_but_their_own_details(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.workers.store'), ['code' => 'W-9', 'name' => 'Jan Nowak', 'is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('workers', ['code' => 'W-9', 'name' => 'Jan Nowak']);
    }

    public function test_the_detail_page_lists_no_certifications(): void
    {
        // Renamed: the section is still rendered, only its contents are empty.
        // The old name promised more than the assertion checked, which is how
        // the button below went unnoticed.
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.workers.show', $worker));

        $response->assertOk();
        $this->assertSame([], $response->viewData('page')['props']['certifications']);
    }

    public function test_the_detail_page_does_not_offer_to_add_a_certification(): void
    {
        // The attach and detach endpoints belong to the module. Offering the
        // button without them sends the supervisor at a route that does not
        // exist — the same failure the shift monitor had with stops that carry
        // no downtime record.
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.workers.show', $worker));

        $response->assertOk();
        $this->assertFalse($response->viewData('page')['props']['canManageCertifications']);
    }

    public function test_the_edit_form_opens_without_the_workforce_module(): void
    {
        // The reported 500. Every other method in the controller guarded the
        // skills relation; edit() was missed, and it is the one screen core
        // keeps from the workforce area.
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.workers.edit', $worker));

        $response->assertOk();
        $this->assertSame([], $response->viewData('page')['props']['worker']['skills']);
    }

    public function test_a_worker_can_be_updated_without_the_workforce_module(): void
    {
        $worker = Worker::factory()->create(['code' => 'W-5', 'name' => 'Before']);

        $this->actingAs($this->admin())
            ->put(route('admin.workers.update', $worker), ['code' => 'W-5', 'name' => 'After', 'is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('workers', ['code' => 'W-5', 'name' => 'After']);
    }

    public function test_a_worker_can_be_deleted_without_the_workforce_module(): void
    {
        $worker = Worker::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.workers.destroy', $worker))
            ->assertRedirect();

        $this->assertSoftDeleted('workers', ['id' => $worker->id]);
    }

    public function test_a_crew_that_was_never_offered_is_a_validation_error_not_a_server_error(): void
    {
        // `exists:crews,id` queried a table this installation does not have, so
        // a value the form could not even offer came back as a 500. Nothing was
        // offered, so nothing validates — and that is a 422.
        $this->actingAs($this->admin())
            ->post(route('admin.workers.store'), [
                'code' => 'W-7', 'name' => 'Jan Nowak', 'is_active' => true, 'crew_id' => 1,
            ])
            ->assertSessionHasErrors('crew_id');

        $this->assertDatabaseMissing('workers', ['code' => 'W-7']);
    }

    public function test_a_skill_that_was_never_offered_is_a_validation_error_not_a_server_error(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.workers.store'), [
                'code' => 'W-8', 'name' => 'Jan Nowak', 'is_active' => true,
                'skills' => [['id' => 1, 'level' => 2]],
            ])
            ->assertSessionHasErrors('skills.0.id');

        $this->assertDatabaseMissing('workers', ['code' => 'W-8']);
    }

    public function test_the_edit_form_is_closed_to_guests_and_non_admins(): void
    {
        $worker = Worker::factory()->create();

        $this->get(route('admin.workers.edit', $worker))->assertRedirect();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get(route('admin.workers.edit', $worker))->assertForbidden();
    }

    /**
     * A stand-in for the module: it records crews, and offers exactly one.
     *
     * Everything above proves the screen survives without the module. These two
     * prove the replacement for `exists:` is not merely inert — that with a
     * module present it accepts what was offered and refuses what was not.
     */
    private function bindProviderOffering(array $crews): void
    {
        $this->app->bind(
            \App\Extension\Contracts\WorkforceProvider::class,
            fn () => new class($crews) extends \App\Extension\Contracts\Null\NullWorkforceProvider
            {
                public function __construct(private array $crews) {}

                public function crewOptions(): array
                {
                    return $this->crews;
                }
            },
        );
    }

    public function test_a_crew_the_pickers_offer_is_accepted(): void
    {
        $this->bindProviderOffering([['id' => 7, 'name' => 'Blue shift']]);

        $this->actingAs($this->admin())
            ->post(route('admin.workers.store'), [
                'code' => 'W-70', 'name' => 'Jan Nowak', 'is_active' => true, 'crew_id' => 7,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('workers', ['code' => 'W-70', 'crew_id' => 7]);
    }

    public function test_a_worker_keeps_a_crew_the_pickers_no_longer_offer(): void
    {
        // crewOptions() serves only active crews. Without carrying the record's
        // own value into the rule, editing somebody assigned to a crew that was
        // later deactivated would stop saving — over a field the form is not
        // even asking them to change.
        $this->bindProviderOffering([['id' => 7, 'name' => 'Blue shift']]);
        $worker = Worker::factory()->create(['code' => 'W-71', 'crew_id' => 99]);

        $this->actingAs($this->admin())
            ->put(route('admin.workers.update', $worker), [
                'code' => 'W-71', 'name' => 'Renamed', 'is_active' => true, 'crew_id' => 99,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('workers', ['code' => 'W-71', 'name' => 'Renamed']);
    }

    public function test_a_crew_nobody_offered_is_still_refused_when_a_module_is_present(): void
    {
        $this->bindProviderOffering([['id' => 7, 'name' => 'Blue shift']]);

        $this->actingAs($this->admin())
            ->post(route('admin.workers.store'), [
                'code' => 'W-72', 'name' => 'Jan Nowak', 'is_active' => true, 'crew_id' => 8,
            ])
            ->assertSessionHasErrors('crew_id');
    }

    public function test_a_guest_is_turned_away(): void
    {
        $this->get(route('admin.workers.index'))->assertRedirect();
    }
}
