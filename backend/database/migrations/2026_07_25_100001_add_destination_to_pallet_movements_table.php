<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Destination changes belong on the same timeline as physical moves (#101),
     * so the ledger answers "who redirected this pallet, and when" with the same
     * authority it already answers "who moved it".
     *
     * A row where from_location === to_location is a destination-only change
     * (re-routed or unassigned without being touched). A move that lands on the
     * pending destination — to_location === from_destination with to_destination
     * back to null — is an arrival.
     *
     * Note that to_destination === null alone does NOT prove an arrival:
     * clearing a destination produces the same shape. The two are only
     * distinguishable via pallets.arrived_at, which is authoritative.
     */
    public function up(): void
    {
        Schema::table('pallet_movements', function (Blueprint $table) {
            $table->string('from_destination', 100)->nullable()->after('to_location')
                ->comment('Destination before this event (#101)');
            $table->string('to_destination', 100)->nullable()->after('from_destination')
                ->comment('Destination after this event; null means unassigned/reached (#101)');
        });
    }

    public function down(): void
    {
        Schema::table('pallet_movements', function (Blueprint $table) {
            $table->dropColumn(['from_destination', 'to_destination']);
        });
    }
};
