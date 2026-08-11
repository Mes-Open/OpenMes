<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stop an issue was escalated from.
 *
 * The shift monitor's Escalate button had no way to record what it was
 * escalating: the only trace was a sentence appended to the description
 * ("Downtime 57 · 12 min"). That is not something the app can read back, so
 * clicking twice — or two supervisors clicking on the same stop — filed
 * separate tickets that maintenance had to reconcile by reading prose, and the
 * timeline drew an escalation balloon per duplicate on the same minute.
 *
 * Nullable: issues are raised from several places that have no stop behind
 * them (inbound inspection, an operator reporting a problem), and those keep
 * writing exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('production_downtime_id')
                ->nullable()
                ->after('work_order_id')
                // The stop going away does not invalidate the investigation it
                // triggered, so the issue outlives it and simply forgets where
                // it came from.
                ->constrained('production_downtimes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_downtime_id');
        });
    }
};
