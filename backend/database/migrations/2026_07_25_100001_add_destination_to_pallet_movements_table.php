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
     * (the pallet was re-routed without being touched); a row where
     * to_destination is null while from_destination was set is an arrival.
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
