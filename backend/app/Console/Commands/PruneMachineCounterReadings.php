<?php

namespace App\Console\Commands;

use App\Models\MachineCounterReading;
use Illuminate\Console\Command;

/**
 * Deletes old counter readings that recorded nothing.
 *
 * A polled counter writes a reading on every poll, and most of them only say
 * the value has not changed. They carry no quantity and nothing reviews or
 * reconciles them, but at one poll a second they add tens of thousands of rows
 * a day per tag and slow every read of the history. Readings that moved a
 * quantity, or need a person (reset, unassigned, blocked, …), are kept.
 */
class PruneMachineCounterReadings extends Command
{
    protected $signature = 'machine-counters:prune-readings {--days=7 : Delete "unchanged" readings older than this}';

    protected $description = 'Delete old machine counter readings that did not change the count';

    private const CHUNK = 5000;

    public function handle(): int
    {
        $days = $this->option('days');
        if (! is_numeric($days) || (int) $days < 1 || (float) $days != (int) $days) {
            $this->error('--days must be a whole number of at least 1.');

            return self::INVALID;
        }

        $cutoff = now()->subDays((int) $days);
        $deleted = 0;

        // In chunks, so a first run on a large table never holds one long lock
        // against the collectors that keep writing to it.
        do {
            $ids = MachineCounterReading::query()
                ->where('status', 'unchanged')
                ->where('created_at', '<', $cutoff)
                ->limit(self::CHUNK)
                ->pluck('id');
            $deleted += MachineCounterReading::whereKey($ids)->delete();
        } while ($ids->count() === self::CHUNK);

        $this->info("Pruned {$deleted} unchanged counter reading(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
