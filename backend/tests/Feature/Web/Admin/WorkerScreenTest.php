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

    public function test_the_detail_page_opens_with_no_certification_section(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.workers.show', $worker));

        $response->assertOk();
        $this->assertSame([], $response->viewData('page')['props']['certifications']);
    }

    public function test_a_guest_is_turned_away(): void
    {
        $this->get(route('admin.workers.index'))->assertRedirect();
    }
}
