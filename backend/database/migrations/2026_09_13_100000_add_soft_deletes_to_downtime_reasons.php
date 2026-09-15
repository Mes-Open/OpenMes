<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make downtime reasons deletable without losing history.
 *
 * The table predates the soft-delete convention and was left out of the sweep
 * that converted the other dictionaries, because nothing could delete a reason:
 * it had no admin screen at all. It is getting one, so it needs the same
 * treatment as every other dictionary a user can edit.
 *
 * A reason is referenced by every downtime recorded against it. Hard-deleting
 * one would orphan months of OEE history and silently change past availability
 * figures; soft-deleting keeps the record intact and simply stops offering the
 * reason to operators.
 *
 * The unique index on `code` becomes partial. Without that a deleted reason
 * keeps its code reserved forever, so a shop that retires "SETUP" and later
 * wants it back cannot have it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('downtime_reasons')) {
            return;
        }

        if (! Schema::hasColumn('downtime_reasons', 'deleted_at')) {
            Schema::table('downtime_reasons', function (Blueprint $table) {
                $table->softDeletes();
                $table->foreignId('deleted_by_id')->nullable()
                    ->comment('User who soft-deleted the row')
                    ->constrained('users')->nullOnDelete();
            });
        }

        $this->makeCodeUniquePartial();
    }

    /**
     * Rebuild the unique index on `code` so it ignores deleted rows.
     *
     * Postgres and SQLite both support partial indexes but name and describe
     * them differently, so each is handled on its own terms rather than through
     * a Schema builder that would flatten the WHERE clause away.
     */
    private function makeCodeUniquePartial(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $indexes = DB::select(<<<'SQL'
                SELECT i.indexname, i.indexdef, (c.conname IS NOT NULL) AS is_constraint
                FROM pg_indexes i
                LEFT JOIN pg_constraint c ON c.conname = i.indexname AND c.contype = 'u'
                WHERE i.schemaname = current_schema()
                  AND i.tablename = 'downtime_reasons'
                  AND i.indexdef LIKE 'CREATE UNIQUE INDEX%'
                  AND i.indexname NOT LIKE '%_pkey'
                SQL);

            foreach ($indexes as $index) {
                if (stripos($index->indexdef, ' WHERE ') !== false) {
                    continue;
                }

                DB::statement($index->is_constraint
                    ? sprintf('ALTER TABLE downtime_reasons DROP CONSTRAINT %s', $index->indexname)
                    : sprintf('DROP INDEX %s', $index->indexname));

                DB::statement($index->indexdef.' WHERE deleted_at IS NULL');
            }

            return;
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select(
                "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'downtime_reasons' AND sql LIKE 'CREATE UNIQUE INDEX%'",
            );

            foreach ($indexes as $index) {
                if (stripos($index->sql, ' where ') !== false) {
                    continue;
                }

                DB::statement(sprintf('DROP INDEX "%s"', $index->name));
                DB::statement($index->sql.' where "deleted_at" is null');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('downtime_reasons') || ! Schema::hasColumn('downtime_reasons', 'deleted_at')) {
            return;
        }

        Schema::table('downtime_reasons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_id');
            $table->dropSoftDeletes();
        });
    }
};
