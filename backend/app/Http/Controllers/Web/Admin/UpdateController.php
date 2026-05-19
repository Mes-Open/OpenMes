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

        if (!$remote) {
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

        // 4. Copy files, preserving protected paths
        $protected = ['storage', 'bootstrap/cache', '.env', 'vendor', 'node_modules'];
        $copyCount = $this->copyDirectory($sourceBackend, $root, $protected);

        // 5. Cleanup temp
        $this->deleteDirectory($tempDir);

        // 6. Run migrations + clear caches
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::warning('Post-update migration failed: ' . $e->getMessage());
        }

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        // 7. Invalidate update check cache
        Cache::forget(self::CACHE_KEY);

        Log::info("System updated to {$version}", ['files_copied' => $copyCount]);

        return redirect()->back()->with('success', __('Updated to :version successfully (:count files updated).', [
            'version' => $version,
            'count' => $copyCount,
        ]));
    }

    /**
     * Recursively copy directory, skipping protected paths.
     */
    private function copyDirectory(string $source, string $dest, array $protected, string $relPath = ''): int
    {
        $count = 0;

        if (! is_dir($dest)) {
            @mkdir($dest, 0755, true);
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
            if (in_array($currentRel, $protected)) {
                continue;
            }

            $srcPath = $source . '/' . $item;
            $dstPath = $dest . '/' . $item;

            if (is_dir($srcPath)) {
                $count += $this->copyDirectory($srcPath, $dstPath, $protected, $currentRel);
            } else {
                if (@copy($srcPath, $dstPath)) {
                    $count++;
                }
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
