<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguish "operator declared actual consumption" from "no declaration yet"
 * (#99). Without this, a genuine zero-consumption declaration is indistinguishable
 * from the unrecorded default, and consumeForBatch would fall back to consuming
 * the whole allocation — losing the returned surplus. The flag lets a recorded
 * zero mean "nothing used, return everything".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            $table->boolean('consumption_recorded')->default(false)->after('consumed_qty');
        });
    }

    public function down(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            $table->dropColumn('consumption_recorded');
        });
    }
};
