<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the location each picked lot was consumed from.
 *
 * A pick's share of a deduction goes to the warehouse its lot sits in. Reading that
 * from the lot every time is wrong the moment the lot is moved: a later correction
 * would credit the store the lot now sits in rather than the one that actually gave
 * the material up, leaving both balances wrong by the corrected quantity.
 *
 * So the answer is written onto the pick the first time it is deducted and reused for
 * every correction and reversal after that — the same reasoning as
 * `material_allocations.consumption_warehouse_id`, one level down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocation_lot_picks', function (Blueprint $table) {
            $table->foreignId('consumption_warehouse_id')->nullable()->after('material_lot_id')
                ->constrained('warehouses')->nullOnDelete()
                ->comment('Location this pick was deducted from, frozen at the first deduction');
        });
    }

    public function down(): void
    {
        Schema::table('allocation_lot_picks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consumption_warehouse_id');
        });
    }
};
