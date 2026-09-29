<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which bench is filling a pallet or a carton right now. Shared state, not a
 * browser's memory: every packing screen sees which operator and bench are
 * filling it, a refresh lands the operator back on their pallet, and a
 * second station does not open a duplicate for the same order.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pallets', 'unit_cartons'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('active_by_id')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('active_workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
                $t->timestamp('activated_at')->nullable();
                $t->index('active_by_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['pallets', 'unit_cartons'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                // The index first: SQLite rebuilds the table on a column drop and
                // trips over an index left on the column.
                $t->dropIndex(['active_by_id']);
                $t->dropConstrainedForeignId('active_by_id');
                $t->dropConstrainedForeignId('active_workstation_id');
                $t->dropColumn('activated_at');
            });
        }
    }
};
