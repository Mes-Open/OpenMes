<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which operator screens a bench shows (queue, workstation, unit_labels,
 * packing). Null means "from the routing": the screens follow the kinds of
 * steps routed to the bench. A list pins them by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->json('operator_screens')->nullable()->after('workstation_type');
        });
    }

    public function down(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->dropColumn('operator_screens');
        });
    }
};
