<?php

namespace App\Console\Commands;

use App\Models\WorkstationDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves stale operator-panel rows on Admin -> Workstation devices to the trash.
 *
 * A panel row is keyed on an id the browser keeps in localStorage, so cleared
 * browser data, a private window or a retired tablet each leave a row behind
 * that never comes back. Rows not seen for the retention period are
 * soft-deleted (restorable from Admin -> Trash); if the browser does return,
 * its heartbeat simply creates a fresh row. Workstation client rows are left
 * alone: those are deliberate installs an admin forgets by hand.
 */
class PruneWorkstationPanels extends Command
{
    protected $signature = 'workstation-devices:prune-panels
        {--days= : Trash panels not seen for this many days (default: config openmmes.workstation_panel_prune_days)}';

    protected $description = 'Move operator-panel devices inactive for too long to the trash';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('openmmes.workstation_panel_prune_days');
        if (! is_numeric($days) || (int) $days < 0 || (float) $days != (int) $days) {
            $this->error('--days must be a whole number of at least 0.');

            return self::INVALID;
        }

        $days = (int) $days;
        if ($days === 0) {
            $this->info('Operator panel pruning is disabled (0 days).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $stale = fn ($q) => $q->where('last_seen_at', '<', $cutoff)
            ->orWhere(fn ($q) => $q->whereNull('last_seen_at')->where('created_at', '<', $cutoff));
        $pruned = 0;

        // One by one through the model, so each delete is audited, broadcast
        // to the live roster and lands in the trash. Stale panels are few.
        // eachById, not each: deleting rows shifts offset-based pages and would
        // skip some.
        WorkstationDevice::panels()->where($stale)->eachById(function (WorkstationDevice $device) use ($stale, &$pruned) {
            // A panel that came back between the scan and now must keep its row
            // (and the name an admin gave it): re-read it locked and only trash
            // it if it is still stale.
            $deleted = DB::transaction(function () use ($device, $stale) {
                $current = WorkstationDevice::panels()
                    ->whereKey($device->getKey())
                    ->where($stale)
                    ->lockForUpdate()
                    ->first();

                return $current?->delete() ?? false;
            });

            if ($deleted) {
                $pruned++;
            }
        });

        $this->info("Moved {$pruned} operator panel(s) inactive for over {$days} day(s) to the trash.");

        return self::SUCCESS;
    }
}
