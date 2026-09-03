<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stock location a production line draws its material from.
 *
 * This is the "workshop stock" half of consumption-by-location: without it there is
 * no way to say which of several storage locations a line's consumption should come
 * off, and everything would have to fall back to one plant-wide default.
 *
 * Nullable on purpose. A plant that does not track stock per location leaves it unset
 * and consumption resolves through the picked lot or the default raw-material
 * warehouse exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('division_id')
                ->constrained()->nullOnDelete()
                ->comment('Stock location this line consumes from');
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
