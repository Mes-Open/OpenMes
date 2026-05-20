<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ApplyUpdateJob;
use App\Models\SystemUpdate;
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

    /**
     * Atomic concurrency guard for the apply flow. TTL matches the Job's
     * 30-minute timeout so a hard-killed worker can never deadlock the system
     * indefinitely. See `update:unlock` for manual recovery.
     */
    public const APPLY_LOCK_KEY = 'update_apply_lock';
    public const APPLY_LOCK_TTL = 1800; // 30 min, matches ApplyUpdateJob::$timeout

    /** States that mean an update is currently in flight. */
    private const ACTIVE_STATES = [
        'queued', 'downloading', 'verifying', 'extracting',
        'backing_up', 'copying', 'installing_dependencies', 'migrating', 'rolling_back',
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
        // Atomic race-free guard: only one apply() can hold this lock at a
        // time. The previous soft-check on STATUS_CACHE_KEY was vulnerable to a
        // TOCTOU race when two admins clicked the button in the same tick.
        // The lock is released by the Job (success, failure, or `failed()`
        // hook) — NOT here. See ApplyUpdateJob::handle()/failed().
        $lock = Cache::lock(self::APPLY_LOCK_KEY, self::APPLY_LOCK_TTL);

        if (! $lock->get()) {
            $current = Cache::get(UpdateApplier::STATUS_CACHE_KEY);
            return redirect()->back()->with('error', __(
                'An update is already in progress (:version, started :time). Refresh to see status.',
                [
                    'version' => is_array($current) ? ($current['version'] ?? '?') : '?',
                    'time'    => is_array($current) ? ($current['started_at'] ?? '?') : '?',
                ]
            ));
        }

        try {
            // Defence-in-depth: if a stale ACTIVE status sits in cache (e.g.
            // the previous worker died but its lock TTL'd out), refuse to
            // dispatch and surface the dangling state to the admin.
            $current = Cache::get(UpdateApplier::STATUS_CACHE_KEY);
            if (is_array($current) && in_array($current['state'] ?? null, self::ACTIVE_STATES, true)) {
                $lock->release();
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
                $lock->release();
                return redirect()->back()->with('error', __(
                    'No update information available. Please check for updates first.'
                ));
            }

            $version = $remote['version'];

            // Initialise the status cache so the banner can immediately switch
            // to progress mode after redirect.
            app(UpdateApplier::class)->initStatus($version, auth()->id() ?? 0);

            ApplyUpdateJob::dispatch($version, $remote, auth()->id() ?? 0);
        } catch (\Throwable $e) {
            // If anything between lock acquisition and successful dispatch
            // blows up, free the lock so we do not strand future admins.
            $lock->release();
            throw $e;
        }

        // NOTE: do NOT release the lock here — the Job owns it now and will
        // forceRelease() it from handle()/failed() once finalised.
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

    /**
     * JSON list of the most recent update runs for the admin audit view.
     * Includes the triggering user so the UI can render "applied by X on Y".
     */
    public function history(): JsonResponse
    {
        $updates = SystemUpdate::with('user:id,name,email')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SystemUpdate $u) => [
                'id'                   => $u->id,
                'from_version'         => $u->from_version,
                'to_version'           => $u->to_version,
                'state'                => $u->state,
                'started_at'           => $u->started_at?->toIso8601String(),
                'finished_at'          => $u->finished_at?->toIso8601String(),
                'duration_seconds'     => $u->duration_seconds,
                'files_copied'         => $u->files_copied,
                'error'                => $u->error,
                'composer_install_ran' => $u->composer_install_ran,
                'checksum_verified'    => $u->checksum_verified,
                'user'                 => $u->user ? [
                    'id'    => $u->user->id,
                    'name'  => $u->user->name,
                    'email' => $u->user->email,
                ] : null,
            ]);

        return response()->json(['updates' => $updates]);
    }
}
