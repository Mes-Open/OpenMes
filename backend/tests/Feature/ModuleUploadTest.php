<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Installing a module from a ZIP through the admin upload form.
 *
 * The controller stores the upload on the `local` disk and hands the
 * installer a path. Those two have to agree: the disk is rooted at
 * storage/app/private, and a path built by hand as storage/app/… pointed at a
 * file that was never there — every upload failed with "Could not open ZIP
 * file" while the same zip installed fine when given a real path.
 */
class ModuleUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Install into a scratch directory, not the repository's modules/.
        $this->modulesPath = sys_get_temp_dir().'/openmes-modules-'.uniqid();
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

    /** A minimal but valid module archive, built the way a vendor would ship it. */
    private function moduleZip(string $name = 'Sample'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mod').'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString("{$name}/module.json", json_encode([
            'name' => $name,
            'display_name' => "{$name} module",
            'version' => '1.0.0',
            'provider' => "Modules\\{$name}\\Providers\\{$name}ServiceProvider",
        ]));
        $zip->addFromString("{$name}/Providers/{$name}ServiceProvider.php", "<?php\nnamespace Modules\\{$name}\\Providers;\nclass {$name}ServiceProvider extends \\Illuminate\\Support\\ServiceProvider {}\n");
        $zip->close();

        return new UploadedFile($path, 'sample.zip', 'application/zip', null, true);
    }

    public function test_an_uploaded_zip_is_installed_into_the_modules_directory(): void
    {
        $response = $this->actingAs($this->admin())
            ->from('/admin/modules')
            ->post('/admin/modules/upload', ['module_zip' => $this->moduleZip()]);

        $response->assertRedirect('/admin/modules');
        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');

        $this->assertFileExists("{$this->modulesPath}/Sample/module.json");
        $this->assertFileExists("{$this->modulesPath}/Sample/Providers/SampleServiceProvider.php");
    }

    public function test_the_stored_upload_is_removed_afterwards(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())
            ->post('/admin/modules/upload', ['module_zip' => $this->moduleZip()]);

        // Nothing left behind in module-uploads/, whether the install succeeded
        // or not — a leaked archive per attempt fills the disk.
        $this->assertSame([], Storage::disk('local')->files('module-uploads'));
    }

    public function test_a_zip_without_a_manifest_reports_the_reason(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mod').'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('Broken/readme.txt', 'no manifest here');
        $zip->close();

        $response = $this->actingAs($this->admin())
            ->from('/admin/modules')
            ->post('/admin/modules/upload', [
                'module_zip' => new UploadedFile($path, 'broken.zip', 'application/zip', null, true),
            ]);

        $response->assertRedirect('/admin/modules');
        $response->assertSessionHas('error', fn (string $msg) => str_contains($msg, 'module.json'));
        $this->assertDirectoryDoesNotExist("{$this->modulesPath}/Broken");
    }

    public function test_a_non_admin_cannot_upload_a_module(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->post('/admin/modules/upload', ['module_zip' => $this->moduleZip()])
            ->assertForbidden();

        $this->assertDirectoryDoesNotExist("{$this->modulesPath}/Sample");
    }
}
