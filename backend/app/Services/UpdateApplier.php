<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Self-update applier.
 *
 * Encapsulates the full apply-update flow (download → verify → extract → backup
 * → copy → migrate → cache-clear → prune) so it can run from a queued Job
 * without blocking the HTTP request that triggered it.
 *
 * All state is reported through the shared `update_apply_status` cache key so
 * the admin update banner can poll progress.
 */
class UpdateApplier
{
    public const STATUS_CACHE_KEY = 'update_apply_status';
    public const STATUS_CACHE_TTL = 86400; // 24h
    public const CHECK_CACHE_KEY  = 'update_check_result';

    private const BACKUP_KEEP   = 3;
    private const CHECKSUM_ALGO = 'sha256';

    /**
     * Run the full update flow.
     *
     * Reports progress via Cache; throws on hard failure (Job will mark failed).
     */
    public function run(string $version, array $remote, int $userId): void
    {
        $root = base_path();
        $zipUrl = $remote['zip_url']
            ?? "https://github.com/Mes-Open/OpenMes/archive/refs/tags/{$version}.zip";

        $tempZip = storage_path("app/update-{$version}.zip");
        $tempDir = storage_path("app/update-{$version}");

        // 1. Download
        $this->setStatus([
            'state'    => 'downloading',
            'progress' => 5,
            'message'  => __('Downloading update package…'),
        ]);

        try {
            $response = Http::timeout(120)->withOptions(['sink' => $tempZip])->get($zipUrl);

            if (! $response->ok() || ! file_exists($tempZip) || filesize($tempZip) < 1000) {
                @unlink($tempZip);
                $this->fail(__('Failed to download update package. Check server internet connection.'));
                return;
            }
        } catch (\Throwable $e) {
            @unlink($tempZip);
            Log::error('Update download failed: ' . $e->getMessage());
            $this->fail(__('Failed to download update: :error', ['error' => $e->getMessage()]));
            return;
        }

        // 1b. Verify checksum
        $this->setStatus([
            'state'    => 'verifying',
            'progress' => 20,
            'message'  => __('Verifying integrity…'),
        ]);

        $checksumError = $this->verifyChecksum($tempZip, $remote);
        if ($checksumError !== null) {
            @unlink($tempZip);
            $this->fail($checksumError);
            return;
        }

        // 2. Extract
        $this->setStatus([
            'state'    => 'extracting',
            'progress' => 35,
            'message'  => __('Extracting update package…'),
        ]);

        $zip = new \ZipArchive();
        if ($zip->open($tempZip) !== true) {
            @unlink($tempZip);
            $this->fail(__('Failed to open update package.'));
            return;
        }

        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }

        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($tempZip);

        // 3. Resolve source root
        $extracted   = glob($tempDir . '/*', GLOB_ONLYDIR);
        $sourceRoot  = $extracted[0] ?? $tempDir;
        $sourceBackend = is_dir($sourceRoot . '/backend') ? $sourceRoot . '/backend' : $sourceRoot;

        if (! is_dir($sourceBackend)) {
            $this->deleteDirectory($tempDir);
            $this->fail(__('Invalid update package structure.'));
            return;
        }

        $protected = ['storage', 'bootstrap/cache', '.env', 'vendor', 'node_modules'];

        // 4. Snapshot
        $this->setStatus([
            'state'    => 'backing_up',
            'progress' => 50,
            'message'  => __('Creating safety snapshot…'),
        ]);

        $timestamp = date('Ymd-His');
        $backupDir = storage_path("app/update-backups/{$timestamp}-{$version}");
        $manifest  = ['modified' => [], 'created' => []];

        try {
            if (! is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }

            $manifest = $this->createBackup($sourceBackend, $root, $protected, $backupDir);

            @file_put_contents(
                $backupDir . '/manifest.json',
                json_encode([
                    'version'   => $version,
                    'timestamp' => $timestamp,
                    'modified'  => $manifest['modified'],
                    'created'   => $manifest['created'],
                ], JSON_PRETTY_PRINT)
            );

            Log::info("Update backup created at {$backupDir}", [
                'version'        => $version,
                'modified_count' => count($manifest['modified']),
                'created_count'  => count($manifest['created']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Update backup failed: ' . $e->getMessage());
            $this->deleteDirectory($tempDir);
            $this->deleteDirectory($backupDir);
            $this->fail(__('Failed to create backup before update: :error', ['error' => $e->getMessage()]));
            return;
        }

        // 5. Copy + migrate (atomic — rollback on failure)
        $this->setStatus([
            'state'    => 'copying',
            'progress' => 70,
            'message'  => __('Installing new files…'),
        ]);

        $copyCount = 0;
        try {
            $copyCount = $this->copyDirectory($sourceBackend, $root, $protected);

            $this->setStatus([
                'state'    => 'migrating',
                'progress' => 85,
                'message'  => __('Running database migrations…'),
            ]);

            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::error('Update failed, rolling back', [
                'version' => $version,
                'error'   => $e->getMessage(),
            ]);

            $this->setStatus([
                'state'   => 'rolling_back',
                'message' => __('Update failed — rolling back…'),
                'error'   => $e->getMessage(),
            ]);

            $this->restoreBackup($backupDir, $manifest, $root);

            try {
                Artisan::call('config:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');
                Artisan::call('cache:clear');
            } catch (\Throwable $clearErr) {
                Log::warning('Post-rollback cache clear failed: ' . $clearErr->getMessage());
            }

            $this->deleteDirectory($tempDir);

            Log::error('Update failed, rolled back to previous version', [
                'version'    => $version,
                'backup_dir' => $backupDir,
            ]);

            $this->setStatus([
                'state'    => 'rolled_back',
                'progress' => 100,
                'message'  => __('Update failed and was rolled back: :error', ['error' => $e->getMessage()]),
                'error'    => $e->getMessage(),
            ]);
            return;
        }

        // 6. Cleanup temp
        $this->deleteDirectory($tempDir);

        // 7. Clear caches
        $this->setStatus([
            'state'    => 'migrating',
            'progress' => 95,
            'message'  => __('Clearing caches…'),
        ]);

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        // 8. Drop update-check cache so banner refreshes
        Cache::forget(self::CHECK_CACHE_KEY);

        // 9. Prune backups
        $this->pruneBackups(self::BACKUP_KEEP);

        Log::info("System updated to {$version}", ['files_copied' => $copyCount]);

        $this->setStatus([
            'state'        => 'completed',
            'progress'     => 100,
            'message'      => __('Updated to :version successfully (:count files updated).', [
                'version' => $version,
                'count'   => $copyCount,
            ]),
            'files_copied' => $copyCount,
            'error'        => null,
        ]);
    }

    /**
     * Initialise the status cache before dispatching the Job.
     * Called by the controller so the banner sees the queued state immediately.
     */
    public function initStatus(string $version, int $userId): void
    {
        $now = now()->toIso8601String();

        Cache::put(self::STATUS_CACHE_KEY, [
            'state'              => 'queued',
            'version'            => $version,
            'started_at'         => $now,
            'updated_at'         => $now,
            'progress'           => 0,
            'message'            => __('Queued — waiting for worker.'),
            'error'              => null,
            'started_by_user_id' => $userId,
            'files_copied'       => null,
        ], self::STATUS_CACHE_TTL);
    }

    /**
     * Set a hard failure terminal state and return.
     */
    private function fail(string $message): void
    {
        $this->setStatus([
            'state'    => 'failed',
            'progress' => 100,
            'message'  => $message,
            'error'    => $message,
        ]);
    }

    /**
     * Merge a partial status patch into the cached status payload.
     */
    private function setStatus(array $patch): void
    {
        $existing = Cache::get(self::STATUS_CACHE_KEY) ?? [];
        $merged   = array_merge($existing, $patch, [
            'updated_at' => now()->toIso8601String(),
        ]);

        Cache::put(self::STATUS_CACHE_KEY, $merged, self::STATUS_CACHE_TTL);
    }

    /**
     * Verify the downloaded ZIP against the SHA-256 checksum advertised by the release.
     * Returns null when OK or no checksum advertised; returns error message on mismatch.
     */
    private function verifyChecksum(string $zipPath, array $remote): ?string
    {
        $expected = $remote['sha256'] ?? ($remote['assets'][0]['sha256'] ?? null);
        $version  = $remote['version'] ?? 'unknown';

        if (! is_string($expected) || trim($expected) === '') {
            Log::warning("Update release does not advertise sha256 checksum — integrity check skipped for {$version}");
            return null;
        }

        $actual = @hash_file(self::CHECKSUM_ALGO, $zipPath);
        if ($actual === false) {
            Log::error('Update checksum verification failed — unable to hash downloaded file', [
                'version' => $version,
                'path'    => $zipPath,
            ]);
            return __('Update package integrity check failed — file does not match expected checksum. Update aborted.');
        }

        if (strtolower($actual) !== strtolower(trim($expected))) {
            Log::error('Update checksum mismatch', [
                'version'   => $version,
                'expected'  => strtolower(trim($expected)),
                'actual'    => strtolower($actual),
                'algorithm' => self::CHECKSUM_ALGO,
                'size'      => @filesize($zipPath),
            ]);
            return __('Update package integrity check failed — file does not match expected checksum. Update aborted.');
        }

        return null;
    }

    /**
     * Walk $source and snapshot any destination files that would be overwritten,
     * recording new files separately so they can be removed on rollback.
     */
    private function createBackup(string $source, string $dest, array $protected, string $backupDir, string $relPath = ''): array
    {
        $manifest = ['modified' => [], 'created' => []];

        $items = @scandir($source);
        if ($items === false) {
            return $manifest;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $currentRel = $relPath ? $relPath . '/' . $item : $item;

            if (in_array($currentRel, $protected, true)) {
                continue;
            }

            $srcPath = $source . '/' . $item;
            $dstPath = $dest . '/' . $item;

            if (is_dir($srcPath)) {
                $sub = $this->createBackup($srcPath, $dstPath, $protected, $backupDir, $currentRel);
                $manifest['modified'] = array_merge($manifest['modified'], $sub['modified']);
                $manifest['created']  = array_merge($manifest['created'], $sub['created']);
                continue;
            }

            if (file_exists($dstPath)) {
                $backupPath   = $backupDir . '/files/' . $currentRel;
                $backupParent = dirname($backupPath);
                if (! is_dir($backupParent)) {
                    @mkdir($backupParent, 0755, true);
                }

                if (! @copy($dstPath, $backupPath)) {
                    throw new \RuntimeException("Failed to snapshot file: {$currentRel}");
                }

                $manifest['modified'][] = $currentRel;
            } else {
                $manifest['created'][] = $currentRel;
            }
        }

        return $manifest;
    }

    /**
     * Restore files from a backup manifest.
     */
    private function restoreBackup(string $backupDir, array $manifest, string $root): void
    {
        $filesDir = $backupDir . '/files';

        foreach ($manifest['modified'] ?? [] as $rel) {
            $src = $filesDir . '/' . $rel;
            $dst = $root . '/' . $rel;

            if (! file_exists($src)) {
                Log::warning("Rollback: missing backup file {$rel}");
                continue;
            }

            $dstParent = dirname($dst);
            if (! is_dir($dstParent)) {
                @mkdir($dstParent, 0755, true);
            }

            if (! @copy($src, $dst)) {
                Log::warning("Rollback: failed to restore {$rel}");
            }
        }

        foreach ($manifest['created'] ?? [] as $rel) {
            $path = $root . '/' . $rel;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Keep the N most recent backup directories.
     */
    private function pruneBackups(int $keep = 3): void
    {
        $base = storage_path('app/update-backups');
        if (! is_dir($base)) {
            return;
        }

        $entries = @scandir($base);
        if ($entries === false) {
            return;
        }

        $dirs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $base . '/' . $entry;
            if (is_dir($path)) {
                $dirs[$path] = @filemtime($path) ?: 0;
            }
        }

        arsort($dirs);
        $stale = array_slice(array_keys($dirs), $keep);

        foreach ($stale as $path) {
            $this->deleteDirectory($path);
        }
    }

    /**
     * Recursively copy directory, skipping protected paths.
     */
    private function copyDirectory(string $source, string $dest, array $protected, string $relPath = ''): int
    {
        $count = 0;

        if (! is_dir($dest)) {
            if (! @mkdir($dest, 0755, true) && ! is_dir($dest)) {
                throw new \RuntimeException("Failed to create directory: {$dest}");
            }
        }

        $items = @scandir($source);
        if ($items === false) {
            return 0;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $currentRel = $relPath ? $relPath . '/' . $item : $item;

            if (in_array($currentRel, $protected, true)) {
                continue;
            }

            $srcPath = $source . '/' . $item;
            $dstPath = $dest . '/' . $item;

            if (is_dir($srcPath)) {
                $count += $this->copyDirectory($srcPath, $dstPath, $protected, $currentRel);
            } else {
                if (! @copy($srcPath, $dstPath)) {
                    throw new \RuntimeException("Failed to copy file: {$currentRel}");
                }
                $count++;
            }
        }

        return $count;
    }

    /**
     * Recursively delete a directory.
     */
    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
