<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ApplyUpdateJob;
use App\Services\UpdateApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateController extends Controller
{
    private const CHECK_URL = 'https://getopenmes.com/current-version.php';
    private const CACHE_KEY = UpdateApplier::CHECK_CACHE_KEY;
    private const CACHE_TTL = 3600; // 1 hour

    /** States that mean an update is currently in flight. */
    private const ACTIVE_STATES = [
        'queued', 'downloading', 'verifying', 'extracting',
        'backing_up', 'copying', 'migrating', 'rolling_back',
    ];

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
     * Dispatch a background Job that downloads, extracts, copies and migrates
     * the latest release. Returns immediately — UI polls /update/status for
     * progress so we never hit PHP-FPM/nginx request timeouts.
     */
    public function apply(): RedirectResponse
    {
        // Reject if an update is already in flight (avoid double-dispatch).
        $current = Cache::get(UpdateApplier::STATUS_CACHE_KEY);
        if (is_array($current) && in_array($current['state'] ?? null, self::ACTIVE_STATES, true)) {
            return redirect()->back()->with('error', __(
                'An update is already in progress (:version, started :time). Refresh to see status.',
                [
                    'version' => $current['version'] ?? '?',
                    'time'    => $current['started_at'] ?? '?',
                ]
            ));
        }

        // Validate that we still have a remote release in cache.
        $remote = Cache::get(self::CACHE_KEY);
        if (! $remote || empty($remote['version'])) {
            return redirect()->back()->with('error', __(
                'No update information available. Please check for updates first.'
            ));
        }

        $version = $remote['version'];

        // Initialise the status cache so the banner can immediately switch to
        // progress mode after redirect.
        app(UpdateApplier::class)->initStatus($version, auth()->id() ?? 0);

        ApplyUpdateJob::dispatch($version, $remote, auth()->id() ?? 0);

        return redirect()->back()->with('success', __(
            'Update queued — system will install :version in the background. Refresh to see progress.',
            ['version' => $version]
        ));
    }

    /**
     * JSON status for the admin banner to poll while an update runs.
     */
    public function status(): JsonResponse
    {
        $status = Cache::get(UpdateApplier::STATUS_CACHE_KEY);

        if (! is_array($status)) {
            return response()->json(null);
        }

        return response()->json($status);
    }
}
