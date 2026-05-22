<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            // expected_qty = immutable snapshot of what BOM said we'd need.
            // For new rows it's set to allocated_qty; partial returns and
            // adjustments are recorded separately below.
            $table->decimal('expected_qty', 12, 4)->default(0)->after('allocated_qty');

            // What was actually used in production (operator confirms post-step).
            $table->decimal('consumed_qty', 12, 4)->default(0)->after('returned_qty');

            // Manual operator adjustments mid-batch (signed delta vs allocated).
            $table->decimal('adjustment_qty', 12, 4)->default(0)->after('consumed_qty');

            // Material lost in this batch (separate from operational scrap%).
            $table->decimal('scrap_qty', 12, 4)->default(0)->after('adjustment_qty');
        });

        // Backfill: existing rows treat allocated as expected so historical
        // accounting balances.
        DB::statement('UPDATE material_allocations SET expected_qty = allocated_qty WHERE expected_qty = 0');
    }

    public function down(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            $table->dropColumn(['expected_qty', 'consumed_qty', 'adjustment_qty', 'scrap_qty']);
        });
    }
};
