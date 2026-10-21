<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Linking an account to a worker that already exists in HR, and unlinking it.
 *
 * A worker holds at most one live account: the Form Request refuses a second
 * one, the database backs that up, and unlinking keeps the worker row.
 */
class UserWorkerLinkTest extends TestCase
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
            'name' => 'Jan Nowak',
            'username' => 'jnowak',
            'email' => 'jan@example.test',
            'role' => 'Operator',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'worker_code' => '',
            'worker_id' => '',
        ], $overrides);
    }

    public function test_a_new_account_is_linked_to_an_existing_worker(): void
    {
        $worker = Worker::factory()->create(['name' => 'HR Name', 'email' => 'hr@example.test']);

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['worker_id' => (string) $worker->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('username', 'jnowak')->firstOrFail();
        $this->assertSame($worker->id, $user->worker_id);
        $this->assertDatabaseCount('workers', 1);
        // Linking leaves HR's record alone.
        $this->assertDatabaseHas('workers', ['id' => $worker->id, 'name' => 'HR Name', 'email' => 'hr@example.test']);
    }

    public function test_an_existing_account_is_linked_to_an_existing_worker(): void
    {
        $worker = Worker::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->payload([
                'password' => '', 'password_confirmation' => '',
                'worker_id' => $worker->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($worker->id, $user->fresh()->worker_id);
        $this->assertDatabaseCount('workers', 1);
    }

    public function test_a_worker_with_an_account_cannot_be_linked_again(): void
    {
        $worker = Worker::factory()->create();
        User::factory()->create(['worker_id' => $worker->id]);
        $other = User::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), $this->payload(['worker_id' => $worker->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['worker_id' => 'This worker already has a user account.']);

        $this->actingAs($admin)
            ->putJson(route('admin.users.update', $other), $this->payload([
                'password' => '', 'password_confirmation' => '',
                'worker_id' => $worker->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('worker_id');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->payload(['worker_id' => $worker->id]))
            ->assertSessionHasErrors('worker_id');

        $this->assertDatabaseMissing('users', ['username' => 'jnowak']);
        $this->assertNull($other->fresh()->worker_id);
    }

    public function test_creating_and_linking_at_once_is_refused(): void
    {
        $worker = Worker::factory()->create();

        $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), $this->payload([
                'worker_id' => $worker->id,
                'worker_code' => 'NEW-1',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('worker_id');

        $this->assertDatabaseMissing('workers', ['code' => 'NEW-1']);
    }

    public function test_a_missing_or_trashed_worker_cannot_be_linked(): void
    {
        $trashed = Worker::factory()->create();
        $trashed->delete();
        $admin = $this->admin();

        foreach ([$trashed->id, $trashed->id + 1000, 'abc'] as $id) {
            $this->actingAs($admin)
                ->postJson(route('admin.users.store'), $this->payload(['worker_id' => $id]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('worker_id');
        }

        $this->assertDatabaseMissing('users', ['username' => 'jnowak']);
    }

    public function test_resubmitting_the_own_worker_on_edit_passes(): void
    {
        $worker = Worker::factory()->create();
        $user = User::factory()->create(['worker_id' => $worker->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->payload([
                'password' => '', 'password_confirmation' => '',
                'worker_id' => $worker->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($worker->id, $user->fresh()->worker_id);
    }

    public function test_a_trashed_account_frees_its_worker(): void
    {
        $worker = Worker::factory()->create();
        User::factory()->create(['worker_id' => $worker->id])->delete();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('linkableWorkers', fn ($list) => collect($list)->pluck('id')->contains($worker->id)));

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->payload(['worker_id' => $worker->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($worker->id, User::where('username', 'jnowak')->value('worker_id'));
    }

    public function test_forms_offer_only_workers_without_an_account(): void
    {
        $free = Worker::factory()->create(['code' => 'W-FREE']);
        $taken = Worker::factory()->create();
        $user = User::factory()->create(['worker_id' => $taken->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/users/Edit')
                ->where('linkableWorkers', fn ($list) => collect($list)->pluck('id')->all() === [$free->id])
                ->where('user.worker.id', $taken->id)
                ->where('user.worker.name', $taken->name));
    }

    public function test_the_list_shows_the_linked_worker_code(): void
    {
        $worker = Worker::factory()->create(['code' => 'W-1001']);
        User::factory()->create(['worker_id' => $worker->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where("workerCodes.{$worker->id}", 'W-1001'));
    }

    public function test_unlinking_keeps_the_worker(): void
    {
        $worker = Worker::factory()->create();
        $user = User::factory()->create(['worker_id' => $worker->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.users.worker.unlink', $user))
            ->assertRedirect(route('admin.users.edit', $user))
            ->assertSessionHas('success');

        $this->assertNull($user->fresh()->worker_id);
        $this->assertDatabaseHas('workers', ['id' => $worker->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_unlinking_an_account_without_a_worker_changes_nothing(): void
    {
        $user = User::factory()->create(['worker_id' => null]);

        $this->actingAs($this->admin())
            ->delete(route('admin.users.worker.unlink', $user))
            ->assertRedirect(route('admin.users.edit', $user))
            ->assertSessionHas('error');

        $this->assertNull($user->fresh()->worker_id);
    }

    public function test_a_guest_can_neither_link_nor_unlink(): void
    {
        $worker = Worker::factory()->create();
        $linked = User::factory()->create(['worker_id' => Worker::factory()->create()->id]);

        $this->post(route('admin.users.store'), $this->payload(['worker_id' => $worker->id]))
            ->assertRedirect();
        $this->delete(route('admin.users.worker.unlink', $linked))->assertRedirect();

        $this->assertDatabaseMissing('users', ['username' => 'jnowak']);
        $this->assertNotNull($linked->fresh()->worker_id);
    }

    public function test_an_operator_can_neither_link_nor_unlink(): void
    {
        $worker = Worker::factory()->create();
        $linked = User::factory()->create(['worker_id' => Worker::factory()->create()->id]);
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->post(route('admin.users.store'), $this->payload(['worker_id' => $worker->id]))
            ->assertForbidden();
        $this->actingAs($operator)
            ->delete(route('admin.users.worker.unlink', $linked))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'jnowak']);
        $this->assertNotNull($linked->fresh()->worker_id);
    }

    public function test_the_database_refuses_two_live_accounts_on_one_worker(): void
    {
        $worker = Worker::factory()->create();
        User::factory()->create(['worker_id' => $worker->id]);

        $this->expectException(QueryException::class);
        User::factory()->create(['worker_id' => $worker->id]);
    }
}
