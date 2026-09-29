<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The rule set behind the user administration screen.
 *
 * It used to live inline in the controller, in two near-identical copies — one
 * in store(), one in update() — which is both against the project's own rule
 * that validation belongs in a Form Request, and the reason the two copies had
 * quietly drifted. These tests pin the behaviour the copies shared, so moving
 * them into StoreUserRequest / UpdateUserRequest cannot change it unnoticed.
 *
 * Deliberately not skipped when a module is installed: nothing asserted here
 * depends on the workforce module being absent, and a test that silently skips
 * on the developer's own machine is worse than no test.
 */
class UserManagementFormRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'account_type' => 'user',
            'name' => 'Anna Kowalska',
            'username' => 'akowalska',
            'email' => 'anna@example.test',
            'role' => 'Operator',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ], $overrides);
    }

    public function test_an_account_is_created_with_its_worker_details(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload([
                'worker_code' => 'EMP-1',
                'worker_phone' => '+48 600 700 800',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['username' => 'akowalska']);
        $this->assertDatabaseHas('workers', ['code' => 'EMP-1', 'phone' => '+48 600 700 800']);
    }

    /**
     * The worker row is created lazily and linked back onto the account. It is
     * the most fragile part of this controller — two tables, one form — and the
     * only thing that makes `$user->worker` resolve at all.
     */
    public function test_creating_an_account_links_the_worker_row_back_onto_it(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['worker_code' => 'EMP-2']))
            ->assertSessionHasNoErrors();

        $user = User::where('username', 'akowalska')->firstOrFail();
        $worker = Worker::where('code', 'EMP-2')->firstOrFail();

        $this->assertSame($worker->id, $user->worker_id);
        $this->assertSame('anna@example.test', $worker->email);
        $this->assertTrue((bool) $worker->is_active);
    }

    public function test_no_worker_row_is_created_without_a_worker_code(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertNull(User::where('username', 'akowalska')->firstOrFail()->worker_id);
        $this->assertDatabaseCount('workers', 0);
    }

    public function test_a_duplicate_username_is_refused(): void
    {
        User::factory()->create(['username' => 'akowalska']);

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload())
            ->assertSessionHasErrors('username');

        $this->assertDatabaseCount('users', 2); // the admin and the existing account
    }

    public function test_a_duplicate_worker_code_is_refused(): void
    {
        Worker::factory()->create(['code' => 'EMP-3']);

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['worker_code' => 'EMP-3']))
            ->assertSessionHasErrors('worker_code');

        $this->assertDatabaseMissing('users', ['username' => 'akowalska']);
    }

    public function test_an_unconfirmed_password_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['password_confirmation' => 'SomethingElse123!']))
            ->assertSessionHasErrors('password');
    }

    public function test_a_name_with_stray_characters_is_refused(): void
    {
        // The regex is the domain rule: a person's name is letters, not markup.
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['name' => '<script>alert(1)</script>']))
            ->assertSessionHasErrors('name');
    }

    public function test_a_workstation_account_must_name_its_workstation(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['account_type' => 'workstation', 'role' => null]))
            ->assertSessionHasErrors('workstation_id');
    }

    public function test_an_edit_may_keep_its_own_username_and_email(): void
    {
        // The uniqueness rule has to ignore the record being edited, or renaming
        // anything else about an account would fail on its own username.
        $user = User::factory()->create(['username' => 'akowalska', 'email' => 'anna@example.test']);

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->payload([
                'name' => 'Anna Nowak',
                'password' => null,
                'password_confirmation' => null,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Anna Nowak']);
    }

    public function test_an_edit_may_keep_its_own_worker_code(): void
    {
        $worker = Worker::factory()->create(['code' => 'EMP-4']);
        $user = User::factory()->create(['worker_id' => $worker->id, 'username' => 'akowalska', 'email' => 'anna@example.test']);

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->payload([
                'worker_code' => 'EMP-4',
                'worker_phone' => '111222333',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('workers', ['id' => $worker->id, 'phone' => '111222333']);
        $this->assertDatabaseCount('workers', 1);
    }

    public function test_an_edit_leaving_the_password_blank_keeps_the_old_one(): void
    {
        $user = User::factory()->create(['username' => 'akowalska', 'email' => 'anna@example.test']);
        $before = $user->password;

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->payload([
                'password' => null,
                'password_confirmation' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_an_edit_may_not_take_another_accounts_username(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => 'akowalska', 'email' => 'anna@example.test']);

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->payload(['username' => 'taken']))
            ->assertSessionHasErrors('username');
    }

    public function test_a_guest_cannot_create_an_account(): void
    {
        $this->post(route('admin.users.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseMissing('users', ['username' => 'akowalska']);
    }

    public function test_an_operator_cannot_create_an_account(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->post(route('admin.users.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'akowalska']);
    }
}
