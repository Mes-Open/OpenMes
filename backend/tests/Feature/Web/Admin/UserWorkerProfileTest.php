<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\User;
use App\Models\Worker;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RequiresNoModules;
use Tests\TestCase;

class UserWorkerProfileTest extends TestCase
{
    use RefreshDatabase;
    use RequiresNoModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfAnyModuleIsInstalled();
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    private function profile(): array
    {
        return [
            'account_type' => 'user',
            'name' => 'Cutting Operator',
            'username' => 'cutting',
            'email' => 'cutting@example.test',
            'role' => 'Operator',
            'worker_code' => 'EMP-CUT',
            'skills' => [],
        ];
    }

    public function test_create_and_edit_worker_profile_without_workforce_module(): void
    {
        $this->post(route('admin.users.store'), $this->profile() + [
            'password' => 'OperatorTest123!',
            'password_confirmation' => 'OperatorTest123!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $user = User::where('username', 'cutting')->firstOrFail();
        $this->assertSame('EMP-CUT', $user->worker->code);
        $this->assertTrue($user->hasRole('Operator'));
        $this->get(route('admin.users.edit', $user))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/users/Edit')->where('user.worker.code', 'EMP-CUT')
                ->where('user.worker.skills', []));
        $this->get(route('admin.users.show', $user))
            ->assertRedirect(route('admin.users.edit', $user));
    }

    public function test_existing_user_can_add_and_update_profile_without_skills_relation(): void
    {
        $user = User::factory()->create();
        $this->put(route('admin.users.update', $user), $this->profile())
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
        $workerId = $user->fresh()->worker_id;
        $this->assertNotNull($workerId);

        $this->put(route('admin.users.update', $user), array_replace($this->profile(), [
            'worker_phone' => '123456789',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
        $this->assertSame($workerId, $user->fresh()->worker_id);
        $this->assertDatabaseHas('workers', ['id' => $workerId, 'phone' => '123456789']);
        $this->assertDatabaseCount('workers', 1);
    }

    public function test_failed_profile_creation_does_not_leave_a_partial_account(): void
    {
        Worker::creating(function () {
            throw new \RuntimeException('Profile storage unavailable');
        });

        $this->post(route('admin.users.store'), $this->profile() + [
            'password' => 'OperatorTest123!',
            'password_confirmation' => 'OperatorTest123!',
        ])->assertStatus(500);

        $this->assertDatabaseMissing('users', ['username' => 'cutting']);
        $this->assertDatabaseMissing('workers', ['code' => 'EMP-CUT']);
    }

    public function test_worker_can_be_assigned_to_station_without_crew_relation(): void
    {
        $line = Line::factory()->create();
        $station = Workstation::factory()->create(['line_id' => $line->id]);
        $worker = Worker::factory()->create(['is_active' => true]);
        $this->get(route('admin.lines.workstations.edit', [$line, $station]))
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/workstations/Edit')->has('workers', 1)
            ->where('workers.0.id', $worker->id)->where('workers.0.crew_name', null));
        $this->put(route('admin.lines.workstations.update', [$line, $station]), [
            'code' => $station->code,
            'name' => $station->name,
            'is_active' => true,
            'worker_ids' => [$worker->id],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.lines.workstations.index', $line));
        $this->assertSame($station->id, $worker->fresh()->workstation_id);
    }
}
