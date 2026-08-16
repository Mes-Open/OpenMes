<?php

namespace Tests\Feature;

use App\Support\TimezoneRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Choosing the plant timezone in the installer.
 *
 * The setting is stored in the database rather than in APP_TIMEZONE because
 * docker-compose sets that variable on every service and a real environment
 * variable overrides the .env file — so on a Docker install the env route cannot
 * be changed from the wizard at all. These tests pin both halves: the wizard
 * collects it, and the stored value is what the application then runs on.
 */
class InstallTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private ?string $installedMarker = null;

    /** path => contents, for every env file this test's requests may rewrite. */
    private array $envBackups = [];

    protected function setUp(): void
    {
        parent::setUp();

        TimezoneRegistry::flush();

        // The wizard refuses to run once the installation is marked complete.
        $marker = storage_path('installed');
        if (file_exists($marker)) {
            $this->installedMarker = file_get_contents($marker);
            unlink($marker);
        }

        // Step 1 rewrites .env (updateEnvFile) and runs key:generate, which
        // rewrites whichever env file the application is actually using — under
        // APP_ENV=testing that is the tracked .env.testing. Back up both, or a
        // test run silently rotates a committed APP_KEY.
        foreach ([base_path('.env'), app()->environmentFilePath()] as $path) {
            if (file_exists($path)) {
                $this->envBackups[$path] = file_get_contents($path);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackups as $path => $contents) {
            file_put_contents($path, $contents);
        }

        $marker = storage_path('installed');
        @unlink($marker);
        if ($this->installedMarker !== null) {
            file_put_contents($marker, $this->installedMarker);
        }

        TimezoneRegistry::flush();

        parent::tearDown();
    }

    public function test_the_wizard_offers_a_timezone_field(): void
    {
        $this->get(route('install.environment'))
            ->assertOk()
            ->assertSee('name="app_timezone"', false)
            ->assertSee('Europe/Warsaw');
    }

    public function test_the_current_timezone_is_preselected(): void
    {
        config(['app.timezone' => 'Europe/Warsaw']);

        $this->get(route('install.environment'))
            ->assertOk()
            ->assertSee('value="Europe/Warsaw" selected', false);
    }

    public function test_a_valid_timezone_is_accepted_and_carried_to_the_database_step(): void
    {
        $this->post(route('install.environment.setup'), [
            'app_name' => 'Plant A',
            'app_url' => 'https://mes.plant.local',
            'app_timezone' => 'Europe/Warsaw',
        ])->assertRedirect(route('install.database'));

        $this->assertSame('Europe/Warsaw', session('install_timezone'));
    }

    public function test_an_unknown_timezone_is_refused(): void
    {
        $this->post(route('install.environment.setup'), [
            'app_name' => 'Plant A',
            'app_url' => 'https://mes.plant.local',
            'app_timezone' => 'Mars/Olympus_Mons',
        ])->assertSessionHasErrors('app_timezone');

        $this->assertNull(session('install_timezone'));
    }

    public function test_the_timezone_is_required(): void
    {
        $this->post(route('install.environment.setup'), [
            'app_name' => 'Plant A',
            'app_url' => 'https://mes.plant.local',
        ])->assertSessionHasErrors('app_timezone');
    }

    // ── The stored setting, which is what the app actually runs on ───────────

    public function test_a_saved_timezone_is_applied_over_the_env_value(): void
    {
        // What a Docker install looks like: compose pinned the container to UTC.
        config(['app.timezone' => 'UTC']);

        TimezoneRegistry::save('Asia/Tokyo');
        TimezoneRegistry::apply();

        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('Asia/Tokyo', date_default_timezone_get());

        date_default_timezone_set('UTC');
    }

    public function test_without_a_stored_setting_the_env_value_stands(): void
    {
        config(['app.timezone' => 'Europe/Warsaw']);

        TimezoneRegistry::apply();

        $this->assertNull(TimezoneRegistry::stored());
        $this->assertSame('Europe/Warsaw', config('app.timezone'));
    }

    public function test_an_unknown_stored_identifier_is_ignored_rather_than_fatal(): void
    {
        // date_default_timezone_set() on an unknown zone is a fatal error, so a
        // hand-edited row must not be able to take the application down.
        DB::table('system_settings')->updateOrInsert(
            ['key' => TimezoneRegistry::SETTING_KEY],
            ['value' => 'Mars/Olympus_Mons', 'updated_at' => now()],
        );
        TimezoneRegistry::flush();

        config(['app.timezone' => 'UTC']);
        TimezoneRegistry::apply();

        $this->assertNull(TimezoneRegistry::stored());
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_saving_an_unknown_identifier_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TimezoneRegistry::save('Mars/Olympus_Mons');
    }

    public function test_the_frontend_receives_the_configured_zone(): void
    {
        TimezoneRegistry::save('Asia/Tokyo');
        TimezoneRegistry::apply();

        // HandleInertiaRequests shares config('app.timezone') as the `timezone`
        // prop, which is what the React date formatters use.
        $this->assertSame('Asia/Tokyo', config('app.timezone'));

        date_default_timezone_set('UTC');
    }
}
