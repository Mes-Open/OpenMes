<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional bin attribution for a material lot. Nullable: a lot can sit in a
 * warehouse before anyone assigns a bin, and old lots stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->foreignId('warehouse_location_id')->nullable()->after('warehouse_id')
                ->constrained('warehouse_locations')->nullOnDelete();
            $table->index(['warehouse_location_id']);
        });
    }

    public function down(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropIndex(['warehouse_location_id']);
            $table->dropConstrainedForeignId('warehouse_location_id');
        });
    }
};
