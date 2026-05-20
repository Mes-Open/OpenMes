<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateController extends Controller
{
    private const CHECK_URL    = 'https://getopenmes.com/current-version.php';
    private const CACHE_KEY    = 'update_check_result';
    private const CACHE_TTL    = 3600; // 1 hour
    private const BACKUP_KEEP  = 3;

    /**
     * Check for available updates (returns JSON, cached 1h).
     */
    public function check(): JsonResponse
    {
        $current = config('version.current', 'v0.0.0');

        $remote = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(5)->get(self::CHECK_URL);
                if ($response->ok()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {
                Log::warning('Update check failed: ' . $e->getMessage());
            }
            return null;
        });

        if (! $remote) {
            return response()->json(['available' => false, 'current' => $current]);
        }

        $latest          = $remote['version'] ?? 'v0.0.0';
        $updateAvailable = version_compare(ltrim($latest, 'v'), ltrim($current, 'v'), '>');

        return response()->json([
            'available'   => $updateAvailable,
            'current'     => $current,
            'latest'      => $latest,
            'name'        => $remote['name'] ?? $latest,
            'release_url' => $remote['release_url'] ?? null,
            'changelog'   => $remote['changelog'] ?? null,
        ]);
    }

    /**
     * Apply the update: download ZIP from GitHub, extract, overwrite files.
     * Works on all environments: XAMPP, Docker, bare metal, Apache2.
     *
     * On any failure during copy or migrate, restores files from snapshot.
     */
    public function apply(): RedirectResponse
    {
        $root = base_path();

        // Get latest release info
        $remote = Cache::get(self::CACHE_KEY);
        if (! $remote || empty($remote['version'])) {
            return redirect()->back()->with('error', __('No update information available. Please check for updates first.'));
        }

        $version = $remote['version'];
        $zipUrl = $remote['zip_url']
            ?? "https://github.com/Mes-Open/OpenMes/archive/refs/tags/{$version}.zip";

        // 1. Download ZIP to temp
        $tempZip = storage_path("app/update-{$version}.zip");
        $tempDir = storage_path("app/update-{$version}");

        try {
            $response = Http::timeout(60)->withOptions(['sink' => $tempZip])->get($zipUrl);

            if (! $response->ok() || ! file_exists($tempZip) || filesize($tempZip) < 1000) {
                @unlink($tempZip);
                return redirect()->back()->with('error', __('Failed to download update package. Check server internet connection.'));
            }
        } catch (\Throwable $e) {
            @unlink($tempZip);
            Log::error('Update download failed: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Failed to download update: :error', ['error' => $e->getMessage()]));
        }

        // 2. Extract ZIP
        $zip = new \ZipArchive();
        if ($zip->open($tempZip) !== true) {
            @unlink($tempZip);
            return redirect()->back()->with('error', __('Failed to open update package.'));
        }

        // Clean previous extraction
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }

        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($tempZip);

        // 3. Find the extracted root (GitHub ZIPs have a prefix directory like "OpenMes-v0.10.0/")
        $extracted = glob($tempDir . '/*', GLOB_ONLYDIR);
        $sourceRoot = $extracted[0] ?? $tempDir;

        // The backend code lives in /backend/ subfolder of the repo
        $sourceBackend = is_dir($sourceRoot . '/backend') ? $sourceRoot . '/backend' : $sourceRoot;

        if (! is_dir($sourceBackend)) {
            $this->deleteDirectory($tempDir);
            return redirect()->back()->with('error', __('Invalid update package structure.'));
        }

        $protected = ['storage', 'bootstrap/cache', '.env', 'vendor', 'node_modules'];

        // 4. Create snapshot of files about to be overwritten/created
        $timestamp = date('Ymd-His');
        $backupDir = storage_path("app/update-backups/{$timestamp}-{$version}");

        try {
            if (! is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }

            $manifest = $this->createBackup($sourceBackend, $root, $protected, $backupDir);

            // Persist manifest to disk for diagnostics / future manual recovery
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
                'version'         => $version,
                'modified_count'  => count($manifest['modified']),
                'created_count'   => count($manifest['created']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Update backup failed: ' . $e->getMessage());
            $this->deleteDirectory($tempDir);
            $this->deleteDirectory($backupDir);
            return redirect()->back()->with('error', __('Failed to create backup before update: :error', ['error' => $e->getMessage()]));
        }

        // 5. Copy + migrate as one atomic unit; rollback on any failure
        $copyCount = 0;
        try {
            $copyCount = $this->copyDirectory($sourceBackend, $root, $protected);
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::error('Update failed, rolling back', [
                'version' => $version,
                'error'   => $e->getMessage(),
            ]);

            $this->restoreBackup($backupDir, $manifest, $root);

            // Best-effort cache flush so any half-applied bytecode/config is gone
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

            return redirect()->back()->with('error', __('Update failed and was rolled back: :error', [
                'error' => $e->getMessage(),
            ]));
        }

        // 6. Cleanup temp ZIP/dir
        $this->deleteDirectory($tempDir);

        // 7. Clear caches
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        // 8. Invalidate update check cache
        Cache::forget(self::CACHE_KEY);

        // 9. Keep last N backups
        $this->pruneBackups(self::BACKUP_KEEP);

        Log::info("System updated to {$version}", ['files_copied' => $copyCount]);

        return redirect()->back()->with('success', __('Updated to :version successfully (:count files updated).', [
            'version' => $version,
            'count' => $copyCount,
        ]));
    }

    /**
     * Walk $source like copyDirectory would, but instead of copying:
     *   - if destination file exists, copy it into $backupDir preserving relative path
     *   - if destination file does NOT exist, record it as "created" (to be deleted on rollback)
     *
     * Returns manifest with relative paths: ['modified' => [...], 'created' => [...]].
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
                $backupPath = $backupDir . '/files/' . $currentRel;
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
     * Restore files from a backup manifest:
     *   - copy each "modified" file back from $backupDir/files/{rel} to $root/{rel}
     *   - delete each "created" file from $root/{rel} (and prune empty dirs we just made)
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
     * Keep the N most recent backup directories, delete older ones.
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

            // Skip protected paths
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
