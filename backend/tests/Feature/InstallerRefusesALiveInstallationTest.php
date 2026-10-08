<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The installer must not run over an installation that is in use.
 *
 * What normally shuts it is a marker file in `storage/`, and a volume can be
 * lost. Its steps rewrite the environment file, run `migrate:fresh` and create
 * an administrator, none of them behind a session -- so with the marker gone,
 * the first visitor could wipe the database or make themselves its admin.
 */
class InstallerRefusesALiveInstallationTest extends TestCase
{
    use RefreshDatabase;

    private ?string $installedMarker = null;

    /** path => contents, for every env file a request here could rewrite. */
    private array $envBackups = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The state under test: a running installation whose marker is gone.
        $marker = storage_path('installed');

        if (file_exists($marker)) {
            $this->installedMarker = file_get_contents($marker);
            unlink($marker);
        }

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

        @unlink(storage_path('installed'));

        if ($this->installedMarker !== null) {
            file_put_contents(storage_path('installed'), $this->installedMarker);
        }

        parent::tearDown();
    }

    public function test_the_database_step_does_not_drop_a_database_that_has_accounts(): void
    {
        $existing = User::factory()->create();

        $this->post(route('install.database.setup'), [
            'db_driver' => 'sqlite',
            'db_database' => 'reinstall.sqlite',
        ])->assertRedirect('/');

        $this->assertNotNull(User::find($existing->id), 'migrate:fresh must not have run');
    }

    public function test_the_admin_step_does_not_hand_out_a_second_administrator(): void
    {
        User::factory()->create();

        $this->withSession(['install_step_1_completed' => true])
            ->post(route('install.admin.create'), [
                'admin_username' => 'intruder',
                'admin_email' => 'intruder@example.test',
                'admin_password' => 'Password123!',
                'admin_password_confirmation' => 'Password123!',
                'site_name' => 'Taken Over',
                'site_url' => 'https://attacker.example',
            ])->assertRedirect('/');

        $this->assertFalse(User::query()->where('username', 'intruder')->exists());
    }

    public function test_the_environment_step_does_not_rewrite_the_configuration(): void
    {
        User::factory()->create();

        $before = file_get_contents(base_path('.env'));

        $this->post(route('install.environment.setup'), [
            'app_name' => 'Taken Over',
            'app_url' => 'https://attacker.example',
            'app_timezone' => 'UTC',
        ])->assertRedirect('/');

        $this->assertSame($before, file_get_contents(base_path('.env')));
    }

    public function test_finding_accounts_puts_the_marker_back(): void
    {
        User::factory()->create();

        $this->post(route('install.database.setup'), ['db_driver' => 'sqlite', 'db_database' => 'reinstall.sqlite']);

        $this->assertFileExists(storage_path('installed'));

        // From here on the wizard is shut the ordinary way.
        $this->get(route('install.index'))->assertRedirect('/');
    }

    public function test_an_empty_installation_can_still_be_set_up(): void
    {
        // No accounts: this is what the installer is for.
        $this->get(route('install.environment'))->assertOk();
        $this->assertFileDoesNotExist(storage_path('installed'));
    }
}
