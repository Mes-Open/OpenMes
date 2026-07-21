<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Unattended install via INSTALLER_PRESET (desktop app / containers):
 * the wizard must skip the environment+database steps, never rewrite .env,
 * and drop the user straight on the admin-account form.
 */
class InstallPresetTest extends TestCase
{
    use RefreshDatabase;

    /** Preserve a pre-existing storage/installed marker across the test run. */
    private ?string $installedMarker = null;

    protected function setUp(): void
    {
        parent::setUp();

        $marker = storage_path('installed');
        if (file_exists($marker)) {
            $this->installedMarker = file_get_contents($marker);
            unlink($marker);
        }
    }

    protected function tearDown(): void
    {
        putenv('INSTALLER_PRESET');
        unset($_ENV['INSTALLER_PRESET'], $_SERVER['INSTALLER_PRESET']);

        $marker = storage_path('installed');
        @unlink($marker);
        if ($this->installedMarker !== null) {
            file_put_contents($marker, $this->installedMarker);
        }

        parent::tearDown();
    }

    private function enablePreset(string $driver = 'sqlite'): void
    {
        putenv("INSTALLER_PRESET={$driver}");
        $_ENV['INSTALLER_PRESET'] = $driver;
        $_SERVER['INSTALLER_PRESET'] = $driver;
    }

    /** Preset installs must not require site_name/site_url — desktop has no "site". */
    private function validAdminPayload(array $overrides = []): array
    {
        return array_merge([
            'admin_username' => 'admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'super-secret-1',
            'admin_password_confirmation' => 'super-secret-1',
        ], $overrides);
    }

    public function test_preset_skips_wizard_and_redirects_to_admin_step(): void
    {
        $this->enablePreset();

        $this->get(route('install.index'))
            ->assertRedirect(route('install.admin'))
            ->assertSessionHas('install_step_1_completed', true)
            ->assertSessionHas('install_database_configured', true);

        // Seeders ran: roles exist without visiting the database step.
        $this->assertNotNull(Role::where('name', 'Admin')->first());

        // Admin form renders without the site name/URL fields.
        $this->get(route('install.admin'))
            ->assertOk()
            ->assertDontSee('name="site_url"', false)
            ->assertDontSee('name="site_name"', false);
    }

    public function test_preset_full_install_creates_admin_and_leaves_env_untouched(): void
    {
        $this->enablePreset();
        $envBefore = file_get_contents(base_path('.env'));

        $this->get(route('install.index'));

        $this->post(route('install.admin.create'), $this->validAdminPayload())
            ->assertRedirect(route('install.complete'));

        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('Admin'));
        $this->assertFileExists(storage_path('installed'));

        $this->assertSame(
            $envBefore,
            file_get_contents(base_path('.env')),
            'Preset install must not rewrite the .env file.'
        );
    }

    public function test_preset_admin_step_validates_input(): void
    {
        $this->enablePreset();
        $this->get(route('install.index'));

        $this->post(route('install.admin.create'), $this->validAdminPayload([
            'admin_email' => 'not-an-email',
            'admin_password_confirmation' => 'mismatch',
        ]))->assertSessionHasErrors(['admin_email', 'admin_password']);

        $this->assertNull(User::where('username', 'admin')->first());
    }

    public function test_preset_with_existing_users_completes_without_admin_step(): void
    {
        $this->enablePreset();
        User::factory()->create();

        $this->get(route('install.index'))->assertRedirect('/');

        $this->assertFileExists(storage_path('installed'));
    }

    public function test_unsupported_preset_value_is_ignored(): void
    {
        $this->enablePreset('mongodb');

        $this->get(route('install.index'))
            ->assertOk()
            ->assertViewIs('install.welcome');
    }

    public function test_wizard_unchanged_without_preset(): void
    {
        $this->get(route('install.index'))
            ->assertOk()
            ->assertViewIs('install.welcome');
    }
}
