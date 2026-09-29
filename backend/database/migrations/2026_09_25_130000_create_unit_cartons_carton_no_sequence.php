<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carton numbers from a Postgres sequence, the way pallet numbers are: two
 * packing benches opening a carton in the same instant used to compute the
 * same MAX+1 and the second insert failed on the unique number. The sequence
 * starts after the highest number already handed out.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('CREATE SEQUENCE IF NOT EXISTS unit_cartons_carton_no_seq');
        $max = (int) DB::table('unit_cartons')
            ->where('carton_no', 'like', 'CTN-%')
            ->selectRaw('MAX(CAST(SUBSTRING(carton_no FROM 5) AS INTEGER)) AS n')
            ->value('n');
        DB::statement($max > 0
            ? "SELECT setval('unit_cartons_carton_no_seq', {$max}, true)"
            : "SELECT setval('unit_cartons_carton_no_seq', 1, false)");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS unit_cartons_carton_no_seq');
        }
    }
};
