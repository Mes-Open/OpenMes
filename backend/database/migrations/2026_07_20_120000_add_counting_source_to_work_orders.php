<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Declares which path is authoritative for a work order's produced quantity:
 *
 *   operator — manual operator entry only (the historical default; machine
 *              counter signals are logged to machine_events but never applied)
 *   machine  — machine counter deltas drive produced_qty; manual entry blocked
 *   both     — both paths apply (advanced; accepts the double-count trade-off)
 *
 * Defaulting existing rows to 'operator' preserves current behaviour exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('counting_source', 20)
                ->nullable()
                ->default('operator')
                ->after('produced_qty');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('counting_source');
        });
    }
};
