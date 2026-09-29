<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Telemetry goes back to off until somebody says otherwise.
 *
 * It shipped in 0.24.0 as opt-out: a migration seeded `telemetry_enabled` to
 * true and an absent row meant the same. That put every installation on the
 * reporting side of a question nobody had been asked.
 *
 * This flips the stored value once. It is deliberately not conditional on how
 * the row got there, because there is no way to tell a deliberate yes from the
 * seeded one — and between the two, the safe mistake is asking again.
 *
 * Anyone who wants it on turns it on in Settings, or sets OPENMES_TELEMETRY=true
 * to answer before the database exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'telemetry_enabled'],
            ['value' => json_encode(false)],
        );
    }

    public function down(): void
    {
        // No reverse. Turning reporting back on for somebody is not a thing a
        // rollback should do quietly.
    }
};
