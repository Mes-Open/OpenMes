<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The two ends of a module's life that were not guarded.
 *
 * `requires_core` has been in every manifest from the start and nothing read
 * it, so a module built against extension points this core does not have
 * installed cleanly and then quietly did nothing. And uninstall deleted the
 * directory without ever calling the module's own uninstall hook — which is the
 * module's only chance to undo what it did outside its own tables.
 */
class ModuleLifecycleGuardsTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/openmes-lifecycle-'.uniqid();
        mkdir($this->modulesPath);
        $this->app->instance(ModuleManager::class, new class($this->modulesPath) extends ModuleManager
        {
            public function __construct(string $path)
            {
                parent::__construct();
                $this->modulesPath = $path;
            }
        });

        Event::fake();
        ProbeInstaller::$calls = [];
        ProbeInstaller::$throwOnUninstall = false;

        // The alias survives the test that made it — PHP cannot forget a class
        // within a process, and both tests below need it.
        if (! class_exists('Modules\Probe\Installer', false)) {
            class_alias(ProbeInstaller::class, 'Modules\Probe\Installer');
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->modulesPath));

        parent::tearDown();
    }

    private function admin(): User
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    private function moduleZip(string $name, ?string $requiresCore): UploadedFile
    {
        $manifest = [
            'name' => $name,
            'display_name' => "{$name} module",
            'version' => '1.0.0',
            'provider' => "Modules\\{$name}\\Providers\\{$name}ServiceProvider",
        ];

        if ($requiresCore !== null) {
            $manifest['requires_core'] = $requiresCore;
        }

        $path = tempnam(sys_get_temp_dir(), 'mod').'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString("{$name}/module.json", json_encode($manifest));
        $zip->addFromString(
            "{$name}/Providers/{$name}ServiceProvider.php",
            "<?php\nnamespace Modules\\{$name}\\Providers;\nclass {$name}ServiceProvider extends \\Illuminate\\Support\\ServiceProvider {}\n",
        );
        $zip->close();

        return new UploadedFile($path, 'module.zip', 'application/zip', null, true);
    }

    /** Put a module on disk without going through the upload form. */
    private function installOnDisk(string $name, ?string $requiresCore): void
    {
        $manifest = ['name' => $name, 'display_name' => "{$name} module", 'version' => '1.0.0'];

        if ($requiresCore !== null) {
            $manifest['requires_core'] = $requiresCore;
        }

        mkdir("{$this->modulesPath}/{$name}");
        file_put_contents("{$this->modulesPath}/{$name}/module.json", json_encode($manifest));
    }

    public function test_a_module_built_for_a_newer_core_is_refused_at_install(): void
    {
        $response = $this->actingAs($this->admin())
            ->from('/admin/modules')
            ->post('/admin/modules/upload', ['module_zip' => $this->moduleZip('Ahead', '>=99.0.0')]);

        $response->assertSessionHas('error', fn (string $m) => str_contains($m, '99.0.0'));
        $this->assertDirectoryDoesNotExist("{$this->modulesPath}/Ahead");
    }

    public function test_a_module_built_for_this_core_installs(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/modules/upload', ['module_zip' => $this->moduleZip('Fine', '>=0.1.0')])
            ->assertSessionHas('success');

        $this->assertFileExists("{$this->modulesPath}/Fine/module.json");
    }

    public function test_a_module_declaring_nothing_still_installs(): void
    {
        // The normal case, and the one a new check must not break.
        $this->actingAs($this->admin())
            ->post('/admin/modules/upload', ['module_zip' => $this->moduleZip('Quiet', null)])
            ->assertSessionHas('success');

        $this->assertFileExists("{$this->modulesPath}/Quiet/module.json");
    }

    public function test_an_unreadable_requirement_is_refused_rather_than_ignored(): void
    {
        // A typo that quietly meant "any version" would defeat the whole check.
        $this->actingAs($this->admin())
            ->from('/admin/modules')
            ->post('/admin/modules/upload', ['module_zip' => $this->moduleZip('Typo', 'latest')])
            ->assertSessionHas('error');

        $this->assertDirectoryDoesNotExist("{$this->modulesPath}/Typo");
    }

    public function test_a_module_already_on_disk_cannot_be_enabled_on_too_old_a_core(): void
    {
        // Install-time checking does not cover a module shipped with the image,
        // or one installed before core was rolled back. Enabling is the other
        // moment it matters — and the one that runs its migrations.
        $this->installOnDisk('Ahead', '>=99.0.0');

        $this->actingAs($this->admin())
            ->from('/admin/modules')
            ->post('/admin/modules/Ahead/enable')
            ->assertSessionHas('error', fn (string $m) => str_contains($m, '99.0.0'));

        $this->assertNotContains('Ahead', app(ModuleManager::class)->enabledNames());
    }

    public function test_a_module_on_disk_with_a_met_requirement_enables(): void
    {
        $this->installOnDisk('Fine', '>=0.1.0');

        $this->actingAs($this->admin())
            ->post('/admin/modules/Fine/enable')
            ->assertSessionHas('success');

        $this->assertContains('Fine', app(ModuleManager::class)->enabledNames());
    }

    public function test_uninstall_runs_the_modules_own_hook_before_deleting_it(): void
    {
        // After the directory is gone the Installer class cannot be loaded, so
        // the hook would never run at all — which is what used to happen.
        $this->installOnDisk('Probe', null);

        $this->actingAs($this->admin())
            ->delete('/admin/modules/Probe')
            ->assertSessionHas('success');

        $this->assertSame(['uninstall'], ProbeInstaller::$calls);
        $this->assertDirectoryDoesNotExist("{$this->modulesPath}/Probe");
    }

    public function test_the_uninstall_message_says_the_tables_stay(): void
    {
        // Migrations are not rolled back. Saying so is the difference between a
        // deliberate choice and a surprise.
        $this->installOnDisk('Quiet', null);

        $this->actingAs($this->admin())
            ->delete('/admin/modules/Quiet')
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'tables were left in place'));
    }

    public function test_a_failing_uninstall_hook_leaves_the_module_in_place(): void
    {
        // Deleting anyway would run half an uninstall and lose the other half
        // with no way to retry.
        $this->installOnDisk('Probe', null);
        ProbeInstaller::$throwOnUninstall = true;

        $this->actingAs($this->admin())
            ->from('/admin/modules')
            ->delete('/admin/modules/Probe')
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Probe'));

        $this->assertFileExists("{$this->modulesPath}/Probe/module.json");
    }
}

/** Stands in for a module shipping `Modules\<Name>\Installer`. */
class ProbeInstaller
{
    /** @var list<string> */
    public static array $calls = [];

    public static bool $throwOnUninstall = false;

    public function install(): void
    {
        self::$calls[] = 'install';
    }

    public function uninstall(): void
    {
        self::$calls[] = 'uninstall';

        if (self::$throwOnUninstall) {
            throw new \RuntimeException('Probe refuses to go quietly');
        }
    }
}
