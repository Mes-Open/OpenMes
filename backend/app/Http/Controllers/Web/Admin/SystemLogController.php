<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System logs viewer.
 *
 * Three tabs:
 *  - app          → Laravel application log file (storage/logs/laravel*.log)
 *  - failed_jobs  → failed_jobs database table (Laravel queue)
 *  - deployments  → system_updates table (added in updater hardening; may be missing on this branch)
 *
 * Failed jobs can be retried via {@see self::retryFailedJob()} which delegates to
 * `php artisan queue:retry {uuid}` and flashes the result back to the operator.
 *
 * NOTE: Large log files are handled by reading at most the last 2 MB of bytes. Anything
 * older than that window is not exposed via this view — operators should use the host
 * file system or `tail`/`grep` directly for deep historical investigation.
 */
class SystemLogController extends Controller
{
    /** Maximum number of log entries returned to the view. */
    private const MAX_ENTRIES = 1000;

    /** Maximum tail size (bytes) when streaming large log files. */
    private const TAIL_BYTES = 2 * 1024 * 1024; // 2 MB

    /** Per-page size for tabular tabs (failed jobs, deployments). */
    private const PER_PAGE = 50;

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'app');

        if (! in_array($tab, ['app', 'failed_jobs', 'deployments'], true)) {
            abort(404);
        }

        if ($tab === 'app') {
            return $this->renderAppTab($request);
        }

        if ($tab === 'failed_jobs') {
            return $this->renderFailedJobsTab($request);
        }

        return $this->renderDeploymentsTab($request);
    }

    /**
     * AJAX endpoint — returns the last N entries from today's log as JSON.
     */
    public function tail(Request $request)
    {
        $entries = $this->readLogFile(today(), null, null, 100);

        return response()->json([
            'entries' => $entries->values(),
        ]);
    }

    /**
     * Retry a failed job by delegating to `php artisan queue:retry {uuid}`.
     *
     * The UUID is verified against the failed_jobs table before invoking the
     * Artisan command so we always have a deterministic error path even if
     * the operator clicks Retry twice in a row (Laravel deletes the row once
     * the retry is dispatched, so the second click will hit the "not found"
     * branch).
     */
    public function retryFailedJob(string $uuid): RedirectResponse
    {
        $exists = DB::table('failed_jobs')->where('uuid', $uuid)->exists();
        if (! $exists) {
            return redirect()->back()->with('error', __('Failed job not found.'));
        }

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return redirect()->back()->with('success', __('Job :uuid queued for retry.', ['uuid' => substr($uuid, 0, 8)]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tab renderers
    // ─────────────────────────────────────────────────────────────────────────

    private function renderAppTab(Request $request)
    {
        $level = $request->input('level');
        $search = $request->input('search');

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : today();

        $entries = $this->readLogFile($date, $level, $search);
        $availableDates = $this->listLogDates();

        return view('admin.logs.system', [
            'tab' => 'app',
            'entries' => $entries,
            'availableDates' => $availableDates,
            'date' => $date,
            'level' => $level,
            'search' => $search,
        ]);
    }

    private function renderFailedJobsTab(Request $request)
    {
        if (! Schema::hasTable('failed_jobs')) {
            return view('admin.logs.system', [
                'tab' => 'failed_jobs',
                'entries' => new LengthAwarePaginator([], 0, self::PER_PAGE),
                'missing' => true,
            ]);
        }

        $entries = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.logs.system', [
            'tab' => 'failed_jobs',
            'entries' => $entries,
            'missing' => false,
        ]);
    }

    private function renderDeploymentsTab(Request $request)
    {
        // The system_updates table ships with the updater hardening migration which has
        // not landed on this branch. Render an info card instead of erroring.
        // TODO: once the system_updates table lands (updater v0.12+ schema), surface
        // the deployment history here instead of the info card.
        if (! Schema::hasTable('system_updates')) {
            return view('admin.logs.system', [
                'tab' => 'deployments',
                'entries' => new LengthAwarePaginator([], 0, self::PER_PAGE),
                'missing' => true,
            ]);
        }

        $entries = DB::table('system_updates')
            ->orderByDesc('started_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.logs.system', [
            'tab' => 'deployments',
            'entries' => $entries,
            'missing' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Application log reader
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Read the Laravel application log for the given date.
     *
     * @return Collection<int, object>
     */
    private function readLogFile(
        Carbon $date,
        ?string $level = null,
        ?string $search = null,
        int $maxLines = self::MAX_ENTRIES
    ): Collection {
        $path = $this->resolveLogPath($date);

        if ($path === null) {
            return collect();
        }

        $handle = @fopen($path, 'r');
        if (! $handle) {
            return collect();
        }

        $size = @filesize($path) ?: 0;

        // Seek to the last TAIL_BYTES for large files — log files can grow to many MB.
        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
            fgets($handle); // discard potentially partial first line
        }

        $entries = collect();
        $current = null;

        while (($line = fgets($handle)) !== false) {
            // Laravel default formatter: "[2026-05-21 10:30:00] local.ERROR: message {...context...}"
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/', $line, $m)) {
                if ($current !== null) {
                    $entries->push($current);
                }

                $current = (object) [
                    'timestamp' => $m[1],
                    'environment' => $m[2],
                    'level' => strtolower($m[3]),
                    'message' => rtrim($m[4]),
                    'context' => '',
                ];
            } elseif ($current !== null) {
                // Continuation lines (stack trace / JSON payload)
                $current->context .= $line;
            }
        }

        if ($current !== null) {
            $entries->push($current);
        }

        fclose($handle);

        if ($level) {
            $level = strtolower($level);
            $entries = $entries->filter(fn ($e) => $e->level === $level);
        }

        if ($search !== null && $search !== '') {
            $needle = $search;
            $entries = $entries->filter(
                fn ($e) => stripos($e->message, $needle) !== false
                    || stripos($e->context, $needle) !== false
            );
        }

        // Newest first, cap.
        return $entries->reverse()->take($maxLines)->values();
    }

    /**
     * Find the on-disk log file backing $date.
     *
     * Tries the daily-rotated file first, then falls back to laravel.log (single mode).
     */
    private function resolveLogPath(Carbon $date): ?string
    {
        $dateStr = $date->format('Y-m-d');

        $candidates = [
            storage_path("logs/laravel-{$dateStr}.log"),
            storage_path('logs/laravel.log'),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * List the dates available for the application log dropdown.
     *
     * @return array<int, string>
     */
    private function listLogDates(): array
    {
        $dates = [];

        $files = glob(storage_path('logs/laravel-*.log')) ?: [];
        foreach ($files as $file) {
            if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }

        if (is_file(storage_path('logs/laravel.log'))) {
            $dates[] = today()->format('Y-m-d');
        }

        $dates = array_values(array_unique($dates));
        rsort($dates);

        return $dates;
    }
}
