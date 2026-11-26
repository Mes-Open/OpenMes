<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the unique index on live batch LOTs that the migration
 * 2026_11_20_150100_add_unique_lot_number_to_batches leaves out when the
 * database already holds the same LOT on two live batches.
 *
 * Without the index the only guard is the check in BatchLotRules, which two
 * concurrent saves can both pass. Run this once the listed batches are fixed;
 * it refuses (and lists them again) while duplicates remain, and does nothing
 * when the index is already there.
 */
class CreateBatchLotUniqueIndex extends Command
{
    public const INDEX = 'batches_lot_number_unique';

    protected $signature = 'batches:lot-unique-index';

    protected $description = 'Create the unique index on live batch LOTs once duplicate LOTs are fixed';

    public function handle(): int
    {
        if (Schema::hasIndex('batches', self::INDEX)) {
            $this->info('The unique LOT index already exists.');

            return self::SUCCESS;
        }

        $duplicates = DB::table('batches')
            ->whereNull('deleted_at')
            ->whereNotNull('lot_number')
            ->groupBy('lot_number')
            ->havingRaw('count(*) > 1')
            ->orderBy('lot_number')
            ->pluck('lot_number');

        if ($duplicates->isNotEmpty()) {
            $this->error('These LOTs are used by more than one live batch. Give each batch its own LOT, then run this again:');
            foreach ($duplicates as $lot) {
                $this->line('  '.$lot);
            }

            return self::FAILURE;
        }

        DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON batches (lot_number) WHERE deleted_at IS NULL AND lot_number IS NOT NULL');
        $this->info('The unique LOT index was created.');

        return self::SUCCESS;
    }
}
