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
     * Apply the update: git pull + migrate + cache clear.
     */
    public function apply(): RedirectResponse
    {
        $root = base_path();
        $log  = [];

        // 1. git pull
        $gitOutput = [];
        $gitStatus = 0;
        exec('cd ' . escapeshellarg($root) . ' && git pull origin main 2>&1', $gitOutput, $gitStatus);
        $log[] = implode("\n", $gitOutput);

        if ($gitStatus !== 0) {
            return redirect()->back()->with(
                'error',
                'Git pull failed — if you are running Docker, rebuild the image manually: docker compose up -d --build'
            );
        }

        // 2. composer install
        $composerOutput = [];
        exec('cd ' . escapeshellarg($root) . ' && composer install --no-dev --optimize-autoloader 2>&1', $composerOutput);
        $log[] = implode("\n", $composerOutput);

        // 3. artisan
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        // 4. Invalidate update check cache so banner hides immediately
        Cache::forget(self::CACHE_KEY);

        Log::info('System updated', ['log' => implode("\n---\n", $log)]);

        return redirect()->back()->with('success', 'System updated successfully.');
    }
}
