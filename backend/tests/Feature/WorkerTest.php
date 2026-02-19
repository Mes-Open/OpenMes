<?php

namespace Tests\Feature;

use App\Models\Crew;
use App\Models\User;
use App\Models\WageGroup;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Operator', 'guard_name' => 'sanctum']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * Create a Crew for use in worker tests.
     */
    private function createCrew(string $code = 'CRW01', string $name = 'Test Crew'): Crew
    {
        return Crew::create([
            'code'      => $code,
            'name'      => $name,
            'is_active' => true,
        ]);
    }

    /**
     * Create a WageGroup for use in worker tests.
     */
    private function createWageGroup(string $code = 'WG01', string $name = 'Standard Wage'): WageGroup
    {
        return WageGroup::create([
            'code'             => $code,
            'name'             => $name,
            'base_hourly_rate' => 20.00,
            'currency'         => 'PLN',
            'is_active'        => true,
        ]);
    }

    public function test_admin_can_list_workers(): void
    {
        Worker::create([
            'code'      => 'WRK001',
            'name'      => 'John Doe',
            'is_active' => true,
        ]);
        Worker::create([
            'code'      => 'WRK002',
            'name'      => 'Jane Smith',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.workers.index'));

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('Jane Smith');
    }

    public function test_admin_can_create_worker(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.workers.store'), [
            'code'      => 'WRK001',
            'name'      => 'Adam Kowalski',
            'email'     => 'adam@example.com',
            'phone'     => '+48600123456',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.workers.index'));
        $this->assertDatabaseHas('workers', [
            'code'  => 'WRK001',
            'name'  => 'Adam Kowalski',
            'email' => 'adam@example.com',
        ]);
    }

    public function test_admin_can_create_worker_with_crew_and_wage_group(): void
    {
        $crew      = $this->createCrew();
        $wageGroup = $this->createWageGroup();

        $response = $this->actingAs($this->admin)->post(route('admin.workers.store'), [
            'code'          => 'WRK001',
            'name'          => 'Marek Nowak',
            'crew_id'       => $crew->id,
            'wage_group_id' => $wageGroup->id,
            'is_active'     => true,
        ]);

        $response->assertRedirect(route('admin.workers.index'));
        $this->assertDatabaseHas('workers', [
            'code'          => 'WRK001',
            'crew_id'       => $crew->id,
            'wage_group_id' => $wageGroup->id,
        ]);
    }

    public function test_admin_can_update_worker(): void
    {
        $worker = Worker::create([
            'code'      => 'WRK001',
            'name'      => 'Old Name',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.workers.update', $worker), [
            'code'      => 'WRK001',
            'name'      => 'New Name',
            'email'     => 'updated@example.com',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.workers.index'));
        $this->assertDatabaseHas('workers', [
            'id'    => $worker->id,
            'name'  => 'New Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_admin_can_toggle_active(): void
    {
        $worker = Worker::create([
            'code'      => 'WRK001',
            'name'      => 'Active Worker',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.workers.toggle-active', $worker));

        $response->assertRedirect(route('admin.workers.index'));
        $this->assertDatabaseHas('workers', [
            'id'        => $worker->id,
            'is_active' => false,
        ]);

        // Toggle again — should restore active state.
        $this->actingAs($this->admin)
            ->post(route('admin.workers.toggle-active', $worker));

        $this->assertDatabaseHas('workers', [
            'id'        => $worker->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_delete_worker(): void
    {
        $worker = Worker::create([
            'code'      => 'WRK001',
            'name'      => 'Deletable Worker',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.workers.destroy', $worker));

        $response->assertRedirect(route('admin.workers.index'));
        $this->assertDatabaseMissing('workers', ['id' => $worker->id]);
    }

    public function test_worker_code_must_be_unique(): void
    {
        Worker::create([
            'code'      => 'WRK001',
            'name'      => 'Existing Worker',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.workers.store'), [
            'code'      => 'WRK001',
            'name'      => 'Duplicate Worker',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('workers', 1);
    }

    public function test_worker_name_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.workers.store'), [
            'code'      => 'WRK001',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_worker_code_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.workers.store'), [
            'name'      => 'No Code Worker',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_worker_email_must_be_valid_if_provided(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.workers.store'), [
            'code'      => 'WRK001',
            'name'      => 'Bad Email Worker',
            'email'     => 'not-an-email',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_worker_crew_must_exist_if_provided(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.workers.store'), [
            'code'      => 'WRK001',
            'name'      => 'Worker',
            'crew_id'   => 99999,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('crew_id');
    }

    public function test_update_allows_same_code_for_same_worker(): void
    {
        $worker = Worker::create([
            'code'      => 'WRK001',
            'name'      => 'Original Name',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.workers.update', $worker), [
            'code'      => 'WRK001',
            'name'      => 'Renamed Worker',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.workers.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workers', ['id' => $worker->id, 'name' => 'Renamed Worker']);
    }

    public function test_guest_cannot_access_workers(): void
    {
        $response = $this->get(route('admin.workers.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_worker(): void
    {
        $response = $this->post(route('admin.workers.store'), [
            'code'      => 'WRK001',
            'name'      => 'Ghost Worker',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('workers', ['code' => 'WRK001']);
    }
}
