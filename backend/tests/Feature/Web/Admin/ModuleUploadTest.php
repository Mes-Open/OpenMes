<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * POST /admin/modules/upload (Admin → Modules → Install). ModuleManager is mocked:
 * installing for real would write into modules/ and reload workers. Its own ZIP
 * checks (manifest, paths, dangerous calls) are covered by the manager's tests;
 * this covers the upload: validation, handing the manager a readable file,
 * cleaning it up, and access.
 */
class ModuleUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** A real ZIP on disk — `mimes:zip` inspects the content, not the name. */
    private function zipUpload(string $name = 'acme.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'module').'.zip';
        $this->tempFiles[] = $path;

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('Acme/module.json', json_encode(['name' => 'Acme']));
        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    public function test_admin_upload_hands_the_manager_a_readable_zip_and_removes_it(): void
    {
        $seenPath = null;
        $this->mock(ModuleManager::class, function ($mock) use (&$seenPath) {
            $mock->shouldReceive('installFromZip')->once()->andReturnUsing(function (string $path) use (&$seenPath) {
                $seenPath = $path;
                // The regression: the controller built storage/app/… while the
                // local disk stores under storage/app/private/….
                $this->assertFileExists($path);
                $this->assertTrue((new \ZipArchive)->open($path) === true, 'Manager was given a file it cannot open.');

                return 'Acme';
            });
        });

        $this->actingAs($this->admin)
            ->post('/admin/modules/upload', ['module_zip' => $this->zipUpload()])
            ->assertRedirect(route('admin.modules.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($seenPath);
        $this->assertFileDoesNotExist($seenPath);
    }

    public function test_a_rejected_zip_reports_the_reason_and_is_removed(): void
    {
        $seenPath = null;
        $this->mock(ModuleManager::class, function ($mock) use (&$seenPath) {
            $mock->shouldReceive('installFromZip')->once()->andReturnUsing(function (string $path) use (&$seenPath) {
                $seenPath = $path;
                throw new \RuntimeException('No module.json found inside the ZIP.');
            });
        });

        $this->actingAs($this->admin)
            ->from('/admin/modules/install')
            ->post('/admin/modules/upload', ['module_zip' => $this->zipUpload()])
            ->assertRedirect('/admin/modules/install')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'No module.json found inside the ZIP.'));

        $this->assertFileDoesNotExist($seenPath);
    }

    public function test_file_is_required(): void
    {
        $this->mock(ModuleManager::class, fn ($mock) => $mock->shouldNotReceive('installFromZip'));

        $this->actingAs($this->admin)
            ->from('/admin/modules/install')
            ->post('/admin/modules/upload', [])
            ->assertRedirect('/admin/modules/install')
            ->assertSessionHasErrors('module_zip');
    }

    public function test_a_file_that_is_not_a_zip_is_rejected(): void
    {
        $this->mock(ModuleManager::class, fn ($mock) => $mock->shouldNotReceive('installFromZip'));

        // A real file: UploadedFile::fake() derives its type from the name, so a
        // fake "acme.zip" would pass `mimes:zip` whatever it contains.
        $path = tempnam(sys_get_temp_dir(), 'module');
        $this->tempFiles[] = $path;
        file_put_contents($path, 'not a zip, just text');

        $this->actingAs($this->admin)
            ->postJson('/admin/modules/upload', ['module_zip' => new UploadedFile($path, 'acme.zip', 'application/zip', null, true)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('module_zip');
    }

    public function test_a_zip_over_20_mb_is_rejected(): void
    {
        $this->mock(ModuleManager::class, fn ($mock) => $mock->shouldNotReceive('installFromZip'));

        $upload = $this->zipUpload();
        $oversized = Mockery::mock($upload)->makePartial();
        $oversized->shouldReceive('getSize')->andReturn(20481 * 1024);

        $this->actingAs($this->admin)
            ->postJson('/admin/modules/upload', ['module_zip' => $oversized])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['module_zip' => '20480']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->mock(ModuleManager::class, fn ($mock) => $mock->shouldNotReceive('installFromZip'));

        $this->post('/admin/modules/upload', ['module_zip' => $this->zipUpload()])
            ->assertRedirect('/login');
    }

    public function test_operator_cannot_upload_a_module(): void
    {
        $this->mock(ModuleManager::class, fn ($mock) => $mock->shouldNotReceive('installFromZip'));
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->postJson('/admin/modules/upload', ['module_zip' => $this->zipUpload()])
            ->assertStatus(403);
    }
}
