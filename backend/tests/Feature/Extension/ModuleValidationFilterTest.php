<?php

namespace Tests\Feature\Extension;

use App\Extension\FilterRegistry;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The seam that lets a module add a field to a core form.
 *
 * `validated()` returns only the keys the rule set names, so a field nobody
 * declared is dropped between the browser and the controller — silently, with no
 * error to show for it. That is the whole reason this seam exists: a module
 * registers its rule through FilterRegistry, and the key survives.
 *
 * A module shipped as a ZIP cannot deliver working React into a released
 * install, so its field has to be rendered by core code from data the module
 * supplies. This is the validation half of that arrangement.
 */
class ModuleValidationFilterTest extends TestCase
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

    /** Stand in for an installed module contributing one field. */
    private function moduleContributes(string $filter, array $rules, ?callable $spy = null): void
    {
        app(FilterRegistry::class)->addFilter($filter, function ($coreRules, $context) use ($rules, $spy) {
            if ($spy) {
                $spy($context);
            }

            return $coreRules + $rules;
        });
    }

    /** @return array<string, mixed> */
    private function userPayload(array $overrides = []): array
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

    public function test_an_undeclared_field_is_dropped_rather_than_refused(): void
    {
        // The starting point, and the reason the seam is needed. Nothing objects
        // to the extra key; it simply never reaches the controller.
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->userPayload(['module_example_code' => 'ABC123']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['username' => 'akowalska']);
    }

    public function test_a_module_rule_makes_its_field_survive_validation(): void
    {
        $this->moduleContributes('validation.admin.users', ['module_example_code' => ['required', 'string', 'max:32']]);

        // The rule is enforced, which is only possible if the key reached the
        // validator — with the key dropped there would be nothing to complain
        // about and the request would pass.
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->userPayload())
            ->assertSessionHasErrors('module_example_code');

        $this->assertDatabaseMissing('users', ['username' => 'akowalska']);
    }

    public function test_a_module_field_that_passes_its_rule_is_accepted(): void
    {
        $this->moduleContributes('validation.admin.users', ['module_example_code' => ['required', 'string', 'max:8']]);

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->userPayload(['module_example_code' => 'ABC123']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['username' => 'akowalska']);
    }

    public function test_a_module_field_is_validated_server_side(): void
    {
        // The point of routing this through the rule set rather than trusting
        // the page: the module's own constraint is enforced by the backend.
        $this->moduleContributes('validation.admin.users', ['module_example_code' => ['required', 'string', 'max:8']]);

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->userPayload(['module_example_code' => str_repeat('X', 40)]))
            ->assertSessionHasErrors('module_example_code');
    }

    public function test_the_module_is_told_which_record_is_being_edited(): void
    {
        // Without the record a module cannot write its own `unique … ignore`,
        // so editing somebody would collide with their own stored value.
        $user = User::factory()->create(['username' => 'akowalska', 'email' => 'anna@example.test']);
        $context = null;

        $this->moduleContributes('validation.admin.users', [], function ($ctx) use (&$context) {
            $context = $ctx;
        });

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->userPayload(['password' => null, 'password_confirmation' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame('update', $context['action']);
        $this->assertSame($user->id, $context['user']?->id);
    }

    public function test_a_create_reports_itself_as_one(): void
    {
        $context = null;
        $this->moduleContributes('validation.admin.users', [], function ($ctx) use (&$context) {
            $context = $ctx;
        });

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->userPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('store', $context['action']);
        $this->assertNull($context['user']);
    }

    public function test_the_worker_form_carries_the_same_seam(): void
    {
        $this->moduleContributes('validation.admin.workers', ['module_example_code' => ['required', 'string']]);

        $this->actingAs($this->admin())
            ->post(route('admin.workers.store'), ['code' => 'W-1', 'name' => 'Jan Nowak', 'is_active' => true])
            ->assertSessionHasErrors('module_example_code');

        $this->assertDatabaseMissing('workers', ['code' => 'W-1']);
    }

    public function test_editing_a_worker_carries_the_record_too(): void
    {
        $worker = Worker::factory()->create(['code' => 'W-2']);
        $context = null;

        $this->moduleContributes('validation.admin.workers', [], function ($ctx) use (&$context) {
            $context = $ctx;
        });

        $this->actingAs($this->admin())
            ->put(route('admin.workers.update', $worker), ['code' => 'W-2', 'name' => 'Renamed', 'is_active' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame('update', $context['action']);
        $this->assertSame($worker->id, $context['worker']?->id);
    }

    public function test_core_rules_still_apply_with_a_module_present(): void
    {
        // A module adding a field must not cost the form its own validation.
        $this->moduleContributes('validation.admin.users', ['module_example_code' => ['nullable', 'string']]);

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->userPayload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');
    }

    public function test_no_module_leaves_the_rule_set_untouched(): void
    {
        // The community path: FilterRegistry returns the array as given.
        $this->assertFalse(app(FilterRegistry::class)->has('validation.admin.users'));

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->userPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }
}
