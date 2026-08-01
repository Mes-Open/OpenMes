<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a pallet is *heading* (#101), alongside the existing `location`
     * which records where it is right now.
     *
     * `destination` is free text on the same scale as `location` (there is no
     * locations table yet), and is cleared the moment a movement lands the
     * pallet on it — a pallet with a non-null destination is in transit, one
     * with a null destination and a stamped `arrived_at` has completed its last
     * assigned run. Keeping the two columns orthogonal means the logistics view
     * can filter "in transit" without comparing strings on every row.
     */
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->string('destination', 100)->nullable()->after('location')
                ->comment('Where the pallet should go; null once reached (#101)');
            $table->timestamp('arrived_at')->nullable()->after('destination')
                ->comment('When the pallet last reached its assigned destination (#101)');

            // The logistics view lists pallets by status and location, and
            // singles out the ones still in transit.
            $table->index('location');
            $table->index('destination');
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropIndex(['location']);
            $table->dropIndex(['destination']);
            $table->dropColumn(['destination', 'arrived_at']);
        });
    }
};
