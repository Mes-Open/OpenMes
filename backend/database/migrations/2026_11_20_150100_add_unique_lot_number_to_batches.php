<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * A batch LOT identifies one batch. Partial, so a deleted batch frees its LOT
     * and batches without a LOT yet are not compared.
     *
     * A database that already holds the same LOT on two live batches cannot take
     * the index: the migration then leaves it out and logs the LOTs, and the
     * application-level check (Batch saving) still refuses new duplicates. Fix
     * the listed batches and run `php artisan batches:lot-unique-index`, which
     * creates the index.
     */
    public function up(): void
    {
        $duplicates = DB::table('batches')
            ->whereNull('deleted_at')
            ->whereNotNull('lot_number')
            ->groupBy('lot_number')
            ->havingRaw('count(*) > 1')
            ->pluck('lot_number');

        if ($duplicates->isNotEmpty()) {
            Log::warning('batches.lot_number unique index not created: LOT used by more than one batch. Fix them, then run php artisan batches:lot-unique-index', [
                'lots' => $duplicates->take(50)->all(),
            ]);

            return;
        }

        DB::statement('CREATE UNIQUE INDEX batches_lot_number_unique ON batches (lot_number) WHERE deleted_at IS NULL AND lot_number IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS batches_lot_number_unique');
    }
};
