<?php

namespace App\Console\Commands;

use App\Http\Controllers\Web\Admin\UpdateController;
use App\Services\UpdateApplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Emergency manual unlock for the self-update apply flow.
 *
 * Use when an apply Job has died in a way that left the
 * `update_apply_lock` cache lock held (e.g. SIGKILL/OOM before the Job's
 * finally block could run, before its 30-minute TTL expires). After this
 * command runs, admins can dispatch a new apply() from the UI.
 *
 *   php artisan update:unlock
 */
class UpdateUnlock extends Command
{
    protected $signature   = 'update:unlock';
    protected $description = 'Force-release the update apply lock and clear the status cache (emergency use).';

    public function handle(): int
    {
        Cache::lock(UpdateController::APPLY_LOCK_KEY)->forceRelease();
        Cache::forget(UpdateApplier::STATUS_CACHE_KEY);

        $this->info('Update lock released and status cleared.');

        return self::SUCCESS;
    }
}
