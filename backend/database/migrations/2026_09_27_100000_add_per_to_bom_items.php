<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a BOM quantity is counted per. Every line used to be "per finished
 * unit"; packaging on a packing step is naturally "one carton per carton" or
 * "one pallet per pallet", and the conversion to a per-unit figure belongs to
 * the packing step's own capacities, not to the person typing 0.1667.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            $table->string('per', 10)->default('unit')->after('quantity_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            $table->dropColumn('per');
        });
    }
};
