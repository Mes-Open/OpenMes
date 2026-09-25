<?php

namespace Tests\Feature\Extension;

use App\Extension\FilterRegistry;
use App\Extension\HookRegistry;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A module adding a field to the user and worker forms, end to end.
 *
 * Three parts have to line up: the field is shown (HookRegistry), accepted
 * (FilterRegistry, covered by ModuleValidationFilterTest) and stored (the
 * persist hook). This covers the first and the third, and the fact that they
 * cost a community install nothing.
 */
class ModuleFormFieldSeamTest extends TestCase
{
    use RefreshDatabase;

    private const FIELD = 'display.admin.users.form.fields';

    private const SAVED = 'persist.admin.users';

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

    private function contributes(string $hook, callable|array $payload): void
    {
        app(HookRegistry::class)->listen($hook, $payload);
    }

    /**
     * Contributions to one hook, read straight from the page props.
     *
     * Not through assertInertia's dot paths: a hook point is named
     * `display.admin.users.form.fields`, so every segment of the name would be
     * read as another level of nesting.
     *
     * @return list<array<string, mixed>>
     */
    private function contributionsOn(\Illuminate\Testing\TestResponse $response, string $hook): array
    {
        return $response->viewData('page')['props']['hooks'][$hook] ?? [];
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

    public function test_a_community_install_is_sent_no_hooks_at_all(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('hooks', []));
    }

    public function test_a_contributed_field_reaches_the_form(): void
    {
        $this->contributes(self::FIELD, [
            'name' => 'module_example_code',
            'type' => 'text',
            'label' => 'Example field',
            'help' => 'Shown under the field.',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.users.create'))->assertOk();

        $this->assertSame([[
            'name' => 'module_example_code',
            'type' => 'text',
            'label' => 'Example field',
            'help' => 'Shown under the field.',
        ]], $this->contributionsOn($response, self::FIELD));
    }

    public function test_the_edit_form_tells_the_module_whose_record_it_is(): void
    {
        $user = User::factory()->create();

        $this->contributes(self::FIELD, fn ($ctx) => [
            'name' => 'module_example_code',
            'type' => 'text',
            'label' => 'Example field',
            'value' => 'VALUE-FOR-'.$ctx['user']->id,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.users.edit', $user))->assertOk();

        $this->assertSame('VALUE-FOR-'.$user->id, $this->contributionsOn($response, self::FIELD)[0]['value']);
    }

    public function test_a_contribution_cannot_smuggle_anything_but_the_allowed_keys(): void
    {
        // array_intersect_key on the whitelist is the entire defence, and the
        // reason a contribution can never become raw markup or a handler.
        $this->contributes(self::FIELD, [
            'name' => 'module_example_code',
            'type' => 'text',
            'label' => 'Example field',
            'onClick' => 'alert(1)',
            'dangerouslySetInnerHTML' => ['__html' => '<script>alert(1)</script>'],
            'className' => 'hidden',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.users.create'))->assertOk();

        $this->assertSame([[
            'name' => 'module_example_code',
            'type' => 'text',
            'label' => 'Example field',
        ]], $this->contributionsOn($response, self::FIELD));
    }

    public function test_a_field_of_an_unrenderable_type_is_dropped_and_logged(): void
    {
        Log::spy();

        $this->contributes(self::FIELD, [
            'name' => 'module_example_code',
            'type' => 'colour-wheel',
            'label' => 'Example field',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('hooks', []));

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_module_is_told_when_an_account_is_saved(): void
    {
        $seen = null;
        $this->contributes(self::SAVED, function ($ctx) use (&$seen) {
            $seen = $ctx;
        });
        app(FilterRegistry::class)->addFilter(
            'validation.admin.users',
            fn ($rules) => $rules + ['module_example_code' => ['nullable', 'string']],
        );

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['module_example_code' => 'ABC123']))
            ->assertSessionHasNoErrors();

        $this->assertSame('store', $seen['action']);
        $this->assertSame('akowalska', $seen['user']->username);
        // The validated input, which is where the module reads its own field —
        // and it is there only because its rule was registered.
        $this->assertSame('ABC123', $seen['input']['module_example_code']);
    }

    public function test_a_module_is_told_when_an_account_is_edited(): void
    {
        $user = User::factory()->create(['username' => 'akowalska', 'email' => 'anna@example.test']);
        $seen = null;
        $this->contributes(self::SAVED, function ($ctx) use (&$seen) {
            $seen = $ctx;
        });

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->payload(['password' => null, 'password_confirmation' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame('update', $seen['action']);
        $this->assertSame($user->id, $seen['user']->id);
    }

    public function test_a_failing_module_write_takes_the_whole_save_with_it(): void
    {
        // The reason dispatch() is called inside the transaction. Without it the
        // account would exist with its module field silently missing, and
        // nothing would say so.
        $this->contributes(self::SAVED, function () {
            throw new \RuntimeException('Module storage unavailable');
        });

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload())
            ->assertStatus(500);

        $this->assertDatabaseMissing('users', ['username' => 'akowalska']);
    }

    public function test_the_worker_form_carries_the_same_two_hooks(): void
    {
        $saved = null;
        $this->contributes('display.admin.workers.form.fields', [
            'name' => 'module_example_code',
            'type' => 'text',
            'label' => 'Example field',
        ]);
        $this->contributes('persist.admin.workers', function ($ctx) use (&$saved) {
            $saved = $ctx;
        });

        $response = $this->actingAs($this->admin())->get(route('admin.workers.create'))->assertOk();

        $this->assertSame(
            'module_example_code',
            $this->contributionsOn($response, 'display.admin.workers.form.fields')[0]['name'],
        );

        $this->actingAs($this->admin())
            ->post(route('admin.workers.store'), ['code' => 'W-1', 'name' => 'Jan Nowak', 'is_active' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame('store', $saved['action']);
        $this->assertSame('W-1', $saved['worker']->code);
    }

    public function test_a_failing_module_write_rolls_a_worker_back_too(): void
    {
        $this->contributes('persist.admin.workers', function () {
            throw new \RuntimeException('Module storage unavailable');
        });

        $this->actingAs($this->admin())
            ->post(route('admin.workers.store'), ['code' => 'W-2', 'name' => 'Jan Nowak', 'is_active' => true])
            ->assertStatus(500);

        $this->assertDatabaseMissing('workers', ['code' => 'W-2']);
    }

    public function test_a_worker_is_still_saved_with_no_module_listening(): void
    {
        // The control: the transaction added around the worker write must not
        // change what happens without a module.
        $this->actingAs($this->admin())
            ->post(route('admin.workers.store'), ['code' => 'W-3', 'name' => 'Jan Nowak', 'is_active' => true])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('workers', ['code' => 'W-3', 'name' => 'Jan Nowak']);
    }

    public function test_editing_a_worker_reports_the_record(): void
    {
        $worker = Worker::factory()->create(['code' => 'W-4']);
        $seen = null;
        $this->contributes('persist.admin.workers', function ($ctx) use (&$seen) {
            $seen = $ctx;
        });

        $this->actingAs($this->admin())
            ->put(route('admin.workers.update', $worker), ['code' => 'W-4', 'name' => 'Renamed', 'is_active' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame('update', $seen['action']);
        $this->assertSame($worker->id, $seen['worker']->id);
    }
}
