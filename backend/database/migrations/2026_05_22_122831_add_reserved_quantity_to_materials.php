<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('reserved_quantity', 12, 3)->default(0)->after('stock_quantity');
        });

        // Backfill from currently-allocated rows so existing tenants see
        // the right reserved value immediately after migration. Subquery
        // form is portable across Postgres and SQLite (tests).
        DB::statement('
            UPDATE materials
            SET reserved_quantity = COALESCE((
                SELECT SUM(allocated_qty - returned_qty)
                FROM material_allocations
                WHERE material_allocations.material_id = materials.id
                  AND material_allocations.status = \'allocated\'
            ), 0)
        ');
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('reserved_quantity');
        });
    }
};
