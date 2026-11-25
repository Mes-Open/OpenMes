<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional bin attribution for a stock balance. Material quantities stay on
 * lot rows (where the lot also records its bin); this column lets non-lot
 * stock, notably finished product, name the bin it sits in without splitting
 * the balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->foreignId('warehouse_location_id')->nullable()->after('warehouse_id')
                ->constrained('warehouse_locations')->nullOnDelete();
            $table->index(['warehouse_location_id']);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->dropIndex(['warehouse_location_id']);
            $table->dropConstrainedForeignId('warehouse_location_id');
        });
    }
};
