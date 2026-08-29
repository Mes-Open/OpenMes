<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `topic_mappings.action_type` was an enum (a CHECK constraint). Relax it to a
 * plain string so new action types — starting with `count_step` — can be added
 * without an enum-alter dance on every driver. Allowed values are enforced at
 * the request-validation layer instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topic_mappings', function (Blueprint $table) {
            $table->string('action_type', 50)->change();
        });

        // The enum leaves a CHECK constraint behind on Postgres; drop it so the
        // new value is accepted. SQLite rebuilt the table via change() already.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE topic_mappings DROP CONSTRAINT IF EXISTS topic_mappings_action_type_check');
        }
    }

    public function down(): void
    {
        // Intentionally left as a plain string — restoring the enum would reject
        // any rows created with newer action types. No-op.
    }
};
