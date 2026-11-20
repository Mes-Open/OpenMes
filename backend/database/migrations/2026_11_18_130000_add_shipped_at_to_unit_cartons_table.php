<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a carton left the plant (#400). A loose carton shipped on a confirmed
 * load is stamped here, so it is refused on any later load even when it holds
 * no serialised units whose status could tell.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_cartons', function (Blueprint $table) {
            $table->timestamp('shipped_at')->nullable()->after('closed_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('unit_cartons', function (Blueprint $table) {
            $table->dropColumn('shipped_at');
        });
    }
};
