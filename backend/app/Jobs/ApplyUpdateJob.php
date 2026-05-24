<?php

namespace App\Jobs;

/*
 * IMPORTANT — queue connection
 *
 * This job MUST run on a queue connection other than `sync` (i.e. `database`,
 * `redis`, etc.) so the HTTP request that triggered the update returns
 * immediately and the long-running download/extract/migrate work happens in a
 * background worker. With `QUEUE_CONNECTION=sync` the Job executes inline
 * inside the controller, which is exactly the timeout problem this refactor
 * is meant to fix.
 *
 * To run async:
 *   - set QUEUE_CONNECTION=database (or redis) in .env
 *   - ensure `jobs` and `failed_jobs` tables exist (php artisan migrate)
 *   - start a worker:  php artisan queue:work --queue=default --timeout=1800
 *     (or run a long-lived Horizon / supervisor / docker-compose `queue` service)
 */

use App\Http\Controllers\Web\Admin\UpdateController;
use App\Services\UpdateApplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApplyUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 30 minutes — full download + extract + migrate budget. */
    public int $timeout = 1800;

    /** No retries — either it works or we roll back inside UpdateApplier. */
    public int $tries = 1;

    public function __construct(
        public string $version,
        public array $remote,
        public int $startedByUserId,
    ) {
    }

    public function handle(UpdateApplier $applier): void
    {
        try {
            try {
                $applier->run($this->version, $this->remote, $this->startedByUserId);
            } catch (\Throwable $e) {
                Log::error('ApplyUpdateJob crashed: ' . $e->getMessage(), [
                    'version' => $this->version,
                    'trace'   => $e->getTraceAsString(),
                ]);

                // Mark cache as failed so the banner stops polling.
                $existing = Cache::get(UpdateApplier::STATUS_CACHE_KEY) ?? [];
                Cache::put(
                    UpdateApplier::STATUS_CACHE_KEY,
                    array_merge($existing, [
                        'state'      => 'failed',
                        'progress'   => 100,
                        'message'    => $e->getMessage(),
                        'error'      => $e->getMessage(),
                        'updated_at' => now()->toIso8601String(),
                    ]),
                    UpdateApplier::STATUS_CACHE_TTL
                );

                throw $e;
            }
        } finally {
            // Always release the concurrency lock acquired by the controller,
            // whether we completed, rolled back, or are about to rethrow. The
            // Job is the sole owner of the lock once dispatched, so a blind
            // forceRelease() is correct here. If we omit this and the Job
            // succeeds, no further updates could run until the 30-min TTL.
            Cache::lock(UpdateController::APPLY_LOCK_KEY)->forceRelease();
        }
    }

    /**
     * Last-ditch handler invoked by the queue worker after `tries` is exhausted
     * or on serialization failures. Ensures the banner always reaches a
     * terminal state.
     */
    public function failed(?\Throwable $e): void
    {
        $message = $e?->getMessage() ?? 'Unknown error';

        Log::error('ApplyUpdateJob marked failed by queue', [
            'version' => $this->version,
            'error'   => $message,
        ]);

        $existing = Cache::get(UpdateApplier::STATUS_CACHE_KEY) ?? [];
        Cache::put(
            UpdateApplier::STATUS_CACHE_KEY,
            array_merge($existing, [
                'state'      => 'failed',
                'progress'   => 100,
                'message'    => $message,
                'error'      => $message,
                'updated_at' => now()->toIso8601String(),
            ]),
            UpdateApplier::STATUS_CACHE_TTL
        );

        // Close out the audit row that `UpdateApplier::run()` couldn't reach
        // (e.g. fatal in the worker, deserialization error, OOM).
        try {
            app(UpdateApplier::class)->markPendingAuditFailed($this->version, $message);
        } catch (\Throwable $auditErr) {
            Log::warning('ApplyUpdateJob::failed audit patch swallowed: ' . $auditErr->getMessage());
        }

        // Belt-and-braces lock release in case handle()'s finally never ran
        // (e.g. deserialization failure before handle() executed). Safe to call
        // even when the lock has already been released.
        Cache::lock(UpdateController::APPLY_LOCK_KEY)->forceRelease();
    }
}
