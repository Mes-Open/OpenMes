<?php

namespace Tests\Unit\Services;

use App\Services\UpdateApplier;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Tests\TestCase;

class UpdateApplierTest extends TestCase
{
    protected UpdateApplier $applier;
    protected ?string $tempZip = null;
    protected array $createdBackupDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->applier = new UpdateApplier();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        if ($this->tempZip && file_exists($this->tempZip)) {
            @unlink($this->tempZip);
        }

        foreach ($this->createdBackupDirs as $dir) {
            $this->recursivelyDelete($dir);
        }

        parent::tearDown();
    }

    private function recursivelyDelete(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursivelyDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function invokePrivate(string $method, array $args)
    {
        $ref = new ReflectionClass($this->applier);
        $m   = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->applier, $args);
    }

    private function makeTempZip(string $content = 'fake-zip-content-1234567890'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'update-applier-test-');
        file_put_contents($path, $content);
        $this->tempZip = $path;
        return $path;
    }

    // ── verifyChecksum ───────────────────────────────────────────────────────

    public function test_verify_checksum_passes_when_no_hash_advertised(): void
    {
        $zip = $this->makeTempZip();

        $result = $this->invokePrivate('verifyChecksum', [$zip, ['version' => 'v1']]);

        $this->assertNull($result);
    }

    public function test_verify_checksum_passes_when_top_level_hash_matches(): void
    {
        $zip  = $this->makeTempZip();
        $hash = hash_file('sha256', $zip);

        $result = $this->invokePrivate('verifyChecksum', [
            $zip,
            ['version' => 'v1', 'sha256' => $hash],
        ]);

        $this->assertNull($result);
    }

    public function test_verify_checksum_passes_with_asset_level_hash(): void
    {
        $zip  = $this->makeTempZip();
        $hash = hash_file('sha256', $zip);

        $result = $this->invokePrivate('verifyChecksum', [
            $zip,
            ['version' => 'v1', 'assets' => [['sha256' => $hash]]],
        ]);

        $this->assertNull($result);
    }

    public function test_verify_checksum_fails_on_mismatch(): void
    {
        $zip = $this->makeTempZip();

        $result = $this->invokePrivate('verifyChecksum', [
            $zip,
            [
                'version' => 'v1',
                'sha256'  => '0000000000000000000000000000000000000000000000000000000000000000',
            ],
        ]);

        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression('/(checksum|integrity)/i', $result);
    }

    public function test_verify_checksum_is_case_insensitive(): void
    {
        $zip  = $this->makeTempZip();
        $hash = strtoupper(hash_file('sha256', $zip));

        $result = $this->invokePrivate('verifyChecksum', [
            $zip,
            ['version' => 'v1', 'sha256' => $hash],
        ]);

        $this->assertNull($result);
    }

    // ── setStatus ────────────────────────────────────────────────────────────

    public function test_status_state_is_persisted_via_set_status(): void
    {
        $this->invokePrivate('setStatus', [[
            'state'    => 'copying',
            'progress' => 50,
        ]]);

        $status = Cache::get(UpdateApplier::STATUS_CACHE_KEY);
        $this->assertIsArray($status);
        $this->assertSame('copying', $status['state']);
        $this->assertSame(50, $status['progress']);
        $this->assertArrayHasKey('updated_at', $status);
        $this->assertNotEmpty($status['updated_at']);
    }

    // ── pruneBackups ─────────────────────────────────────────────────────────

    public function test_prune_backups_keeps_n_most_recent(): void
    {
        $base = storage_path('app/update-backups');
        if (! is_dir($base)) {
            @mkdir($base, 0755, true);
        }

        $names = ['20200101-000000-v1', '20200201-000000-v2', '20200301-000000-v3', '20200401-000000-v4', '20200501-000000-v5'];
        $paths = [];
        foreach ($names as $i => $name) {
            $path = $base . '/' . $name;
            @mkdir($path, 0755, true);
            // Stagger mtime so order is deterministic — older index = older mtime.
            @touch($path, time() - ((count($names) - $i) * 60));
            $paths[] = $path;
            $this->createdBackupDirs[] = $path;
        }

        $this->invokePrivate('pruneBackups', [2]);

        // Two newest (last two we created) should remain; older three deleted.
        $this->assertFileExists($paths[4]);
        $this->assertFileExists($paths[3]);
        $this->assertFileDoesNotExist($paths[2]);
        $this->assertFileDoesNotExist($paths[1]);
        $this->assertFileDoesNotExist($paths[0]);
    }
}
