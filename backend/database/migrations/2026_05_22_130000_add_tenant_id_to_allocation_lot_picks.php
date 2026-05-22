<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Add tenant_id (nullable so we can backfill before any constraint).
        Schema::table('allocation_lot_picks', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('material_lot_id')
                ->constrained()->cascadeOnDelete();
            $table->index('tenant_id');
        });

        // 2) Backfill tenant_id from the parent material_allocation. Portable
        // subquery form so it runs on both Postgres and SQLite (tests).
        DB::statement('
            UPDATE allocation_lot_picks
            SET tenant_id = (
                SELECT tenant_id FROM material_allocations
                WHERE material_allocations.id = allocation_lot_picks.material_allocation_id
            )
            WHERE tenant_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('allocation_lot_picks', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
