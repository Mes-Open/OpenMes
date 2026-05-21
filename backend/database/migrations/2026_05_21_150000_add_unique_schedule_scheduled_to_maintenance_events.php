<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotency for maintenance event generation has been enforced at the
        // application layer (GenerateMaintenanceEvents::run() checks `exists()`
        // before insert). The DB-level unique constraint added below is a
        // defence-in-depth measure that protects against:
        //   - concurrent workers winning the exists() check in lock-step,
        //   - manual SQL inserts that bypass the service layer,
        //   - future refactors that drop the application-level guard.
        //
        // We use a regular composite unique constraint here. NULL semantics in
        // SQL allow multiple rows with schedule_id IS NULL (NULL != NULL), which
        // is exactly what we want — manually created maintenance events without
        // a parent schedule should not be constrained.
        //
        // Dedup any pre-existing duplicates first so the constraint can be added
        // cleanly. This is defensive — on a freshly migrated database there will
        // be no duplicates, but the statement is cheap and guarantees the
        // migration succeeds on production data that may have slipped through
        // before the application-level guard was added.
        DB::statement('
            DELETE FROM maintenance_events
            WHERE id NOT IN (
                SELECT keep_id FROM (
                    SELECT MIN(id) AS keep_id
                    FROM maintenance_events
                    WHERE schedule_id IS NOT NULL
                    GROUP BY schedule_id, scheduled_at
                ) AS keepers
            )
            AND schedule_id IS NOT NULL
        ');

        Schema::table('maintenance_events', function (Blueprint $table) {
            $table->unique(
                ['schedule_id', 'scheduled_at'],
                'maintenance_events_schedule_scheduled_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_events', function (Blueprint $table) {
            $table->dropUnique('maintenance_events_schedule_scheduled_unique');
        });
    }
};
