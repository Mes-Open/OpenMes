<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OctaneReloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
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

    public function test_enabling_a_module_runs_the_migrations_it_ships(): void
    {
        // The bug: enable() called migrate() plainly, on the theory that
        // enabling the module is the first moment its migrations can run. It is
        // not. Migrations are registered by the module's service provider,
        // providers are registered at boot, and this process booted with the
        // module switched off — so migrate saw only the application's own paths
        // and the module's tables were never created. Every screen the module
        // contributes then died on its first query.
        //
        // Asserting the table exists, not that migrate was called: a call is
        // exactly what the broken version also made.
        $this->makeModuleFixture('MigratingFixture', withMigration: true);
        file_put_contents(
            base_path('modules/MigratingFixture/database/migrations/2026_01_01_000000_create_fixture_widgets_table.php'),
            <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('fixture_widgets', fn (Blueprint $t) => $t->id());
                }

                public function down(): void
                {
                    Schema::dropIfExists('fixture_widgets');
                }
            };
            PHP
        );

        $this->assertFalse(Schema::hasTable('fixture_widgets'));

        $this->actingAs($this->admin())->post(route('admin.modules.enable', 'MigratingFixture'));

        $this->assertTrue(
            Schema::hasTable('fixture_widgets'),
            'Enabling the module did not create the table its own migration defines.',
        );
    }

    public function test_the_migrations_a_module_ships_can_be_found_without_booting_it(): void
    {
        $manager = app(\App\Services\ModuleManager::class);

        $this->makeModuleFixture('MigrationFixture', withMigration: true);

        $this->assertSame(
            base_path('modules/MigrationFixture/database/migrations'),
            $manager->migrationsPath('MigrationFixture'),
        );
        $this->assertNull($manager->migrationsPath('ModuleThatShipsNoTables'));
    }

    /** A minimal but real module directory, removed again after the test. */
    private function makeModuleFixture(string $name, bool $withMigration = false): string
    {
        $dir = base_path("modules/{$name}");
        @mkdir($dir, 0775, true);
        $this->fixtures[] = $dir;

        file_put_contents($dir.'/module.json', json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'provider' => "Modules\\{$name}\\Providers\\{$name}ServiceProvider",
        ]));

        if ($withMigration) {
            @mkdir($dir.'/database/migrations', 0775, true);
        }

        return $dir;
    }

    /** @var list<string> */
    private array $fixtures = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->fixtures) as $path) {
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }

        parent::tearDown();
    }

    private function deleteTree(string $dir): void
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
            $path = "{$dir}/{$entry}";
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }

        @rmdir($dir);
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
