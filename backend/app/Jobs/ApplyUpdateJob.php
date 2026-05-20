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
    }
}
