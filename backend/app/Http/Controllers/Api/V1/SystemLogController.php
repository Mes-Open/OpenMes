<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * API mirror of Web/Admin/SystemLogController — read the Laravel application
 * log + failed jobs + deployment history as JSON. Admin-only at the route
 * layer (see routes/api.php).
 *
 * Used by the mobile system-logs viewer at /admin/system-logs.
 */
class SystemLogController extends Controller
{
    private const TAIL_BYTES = 256 * 1024;
    private const MAX_ENTRIES = 500;

    /** Returns the last N parsed log entries from today's app log. */
    public function tail(Request $request): JsonResponse
    {
        $level   = $request->query('level');
        $search  = $request->query('search');
        $date    = $request->filled('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : today();
        $limit   = max(10, min((int) $request->query('limit', 100), self::MAX_ENTRIES));

        $entries = $this->readLogFile($date, $level, $search, $limit);

        return response()->json([
            'data' => $entries->values()->all(),
            'meta' => [
                'date'  => $date->toDateString(),
                'count' => $entries->count(),
            ],
        ]);
    }

    /** Paginated list of failed jobs from the standard Laravel `failed_jobs` table. */
    public function failedJobs(Request $request): JsonResponse
    {
        if (! Schema::hasTable('failed_jobs')) {
            return response()->json([
                'data' => [],
                'meta' => ['missing' => true, 'total' => 0],
            ]);
        }

        $perPage = max(1, min((int) $request->query('per_page', 25), 100));
        $page    = DB::table('failed_jobs')->orderByDesc('failed_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'missing'      => false,
            ],
        ]);
    }

    /** Retry a failed job via `artisan queue:retry {uuid}`. 404 when not found. */
    public function retryFailedJob(string $uuid): JsonResponse
    {
        if (! Schema::hasTable('failed_jobs')) {
            return response()->json(['message' => 'Failed jobs table is not present.'], 404);
        }
        $exists = DB::table('failed_jobs')->where('uuid', $uuid)->exists();
        if (! $exists) {
            return response()->json(['message' => 'Failed job not found.'], 404);
        }

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return response()->json(['message' => "Job {$uuid} queued for retry."]);
    }

    /** Deployment history from system_updates (only present once updater v0.12+ ships). */
    public function deployments(Request $request): JsonResponse
    {
        if (! Schema::hasTable('system_updates')) {
            return response()->json([
                'data' => [],
                'meta' => ['missing' => true, 'total' => 0],
            ]);
        }

        $perPage = max(1, min((int) $request->query('per_page', 25), 100));
        $page    = DB::table('system_updates')->orderByDesc('started_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'missing'      => false,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Log file reader — copy of Web/Admin/SystemLogController::readLogFile
    // (kept here so the API controller is self-contained; if the parser grows
    // more elaborate this should move into a shared LogReader service).
    // ─────────────────────────────────────────────────────────────────────────

    private function readLogFile(
        Carbon $date,
        ?string $level = null,
        ?string $search = null,
        int $maxLines = self::MAX_ENTRIES
    ): Collection {
        $path = $this->resolveLogPath($date);
        if ($path === null) return collect();

        $handle = @fopen($path, 'r');
        if (! $handle) return collect();

        $size = @filesize($path) ?: 0;
        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
            fgets($handle); // discard partial first line
        }

        $entries = collect();
        $current = null;

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/', $line, $m)) {
                if ($current !== null) $entries->push($current);
                $current = (object) [
                    'timestamp'   => $m[1],
                    'environment' => $m[2],
                    'level'       => strtolower($m[3]),
                    'message'     => rtrim($m[4]),
                    'context'     => '',
                ];
            } elseif ($current !== null) {
                $current->context .= $line;
            }
        }
        if ($current !== null) $entries->push($current);
        fclose($handle);

        if ($level) {
            $needle = strtolower($level);
            $entries = $entries->filter(fn ($e) => $e->level === $needle);
        }
        if ($search !== null && $search !== '') {
            $entries = $entries->filter(
                fn ($e) => stripos($e->message, $search) !== false
                    || stripos($e->context, $search) !== false,
            );
        }

        // Tail newest-first, clamped to maxLines.
        return $entries->reverse()->take($maxLines);
    }

    private function resolveLogPath(Carbon $date): ?string
    {
        $base = storage_path('logs');
        // Daily channel filename pattern, fall back to the single laravel.log.
        $candidates = [
            $base . '/laravel-' . $date->format('Y-m-d') . '.log',
            $base . '/laravel.log',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }
        return null;
    }
}
