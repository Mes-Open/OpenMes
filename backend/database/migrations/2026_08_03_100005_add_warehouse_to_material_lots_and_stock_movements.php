<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouse attribution (#212).
 *
 * Material lots gain the warehouse they physically sit in, and every stock
 * movement records which warehouse it moved stock in/out of — without it the
 * ledger cannot be reconciled against per-warehouse balances. Both nullable:
 * rows that predate warehouses (and installs that never create one) stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('source_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('material_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
