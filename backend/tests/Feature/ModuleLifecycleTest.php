<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OctaneReloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Turning a module on and off has to take effect on the running server.
 *
 * Octane keeps the booted application in memory between requests, so clearing
 * caches is not enough — the workers still hold the routes they loaded and the
 * providers they registered at boot. Before this, disabling a module left every
 * one of its pages serving normally until somebody restarted the server by
 * hand. For an optional feature that is an annoyance; for a paid module it is a
 * hole, because "disabled" did not mean the screens stopped answering.
 */
class ModuleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_disabling_a_module_reloads_the_running_workers(): void
    {
        $reloader = $this->spyReloader();

        $this->actingAs($this->admin())
            ->post(route('admin.modules.disable', 'ExampleShowcase'))
            ->assertRedirect(route('admin.modules.index'));

        // The reload rides Octane's own end-of-request event rather than
        // app()->terminating(): Octane flushes the request sandbox before
        // Laravel reaches those callbacks, so a terminating callback would never
        // run and the reload would silently never happen. Firing the event is
        // what a served request does.
        $this->terminateRequest();

        $reloader->shouldHaveReceived('reload');
    }

    public function test_enabling_a_module_reloads_the_running_workers(): void
    {
        $reloader = $this->spyReloader();

        $this->actingAs($this->admin())->post(route('admin.modules.enable', 'ExampleShowcase'));

        $this->terminateRequest();

        $reloader->shouldHaveReceived('reload');
    }

    public function test_the_reload_does_not_go_through_artisan(): void
    {
        // The bug this pins down: the reload used to be
        // Artisan::call('octane:reload'), which throws "command does not exist"
        // inside an HTTP worker — Octane registers its commands only when the
        // application runs in the console. Every module toggle silently did
        // nothing, and whether it appeared to work depended on whether the
        // worker happened to be recycled for an unrelated reason.
        //
        // The old test could not see any of that: PHPUnit runs in the console,
        // where the command does exist, and it asserted the call was made
        // rather than that anything happened. So this asserts the shape of the
        // fix instead — the one thing the test environment *can* check.
        Artisan::spy();
        $this->spyReloader();

        $this->actingAs($this->admin())->post(route('admin.modules.disable', 'ExampleShowcase'));
        $this->terminateRequest();

        Artisan::shouldNotHaveReceived('call', ['octane:reload']);
    }

    /** A stand-in for the reloader, so the test asserts an effect, not a call. */
    private function spyReloader(): \Mockery\MockInterface
    {
        $spy = \Mockery::spy(OctaneReloader::class);
        $this->instance(OctaneReloader::class, $spy);

        return $spy;
    }

    /** What Octane dispatches once the response has been sent. */
    private function terminateRequest(): void
    {
        Event::dispatch(new \Laravel\Octane\Events\RequestTerminated(
            app(), app(), request(), new \Illuminate\Http\Response,
        ));
    }

    public function test_the_route_cache_is_cleared_too(): void
    {
        // A module registers its routes with loadRoutesFrom, which Laravel skips
        // entirely while a route cache is in place — so on a production install
        // the module's own pages would 404 even while enabled.
        Artisan::spy();

        $this->actingAs($this->admin())->post(route('admin.modules.disable', 'ExampleShowcase'));

        Artisan::shouldHaveReceived('call')->with('route:clear');
        Artisan::shouldHaveReceived('call')->with('config:clear');
    }

    public function test_disabling_records_the_change(): void
    {
        $admin = $this->admin();

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'modules_enabled'],
            ['value' => json_encode(['ExampleShowcase']), 'updated_at' => now()],
        );

        $this->actingAs($admin)->post(route('admin.modules.disable', 'ExampleShowcase'));

        $this->assertSame(
            [],
            json_decode(DB::table('system_settings')->where('key', 'modules_enabled')->value('value'), true),
        );
    }

    public function test_a_non_admin_cannot_turn_modules_on_or_off(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->post(route('admin.modules.disable', 'ExampleShowcase'))
            ->assertForbidden();
    }
}
