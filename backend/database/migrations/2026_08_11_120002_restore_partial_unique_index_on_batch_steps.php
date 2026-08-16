<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-assert the partial unique index on batch_steps (batch_id, step_number).
 *
 * 2026_06_15_000002 made every soft-deletable table's unique index partial
 * (WHERE deleted_at IS NULL) so a soft-deleted row no longer occupies its
 * unique value. On SQLite, adding the ISA-95 foreign-key columns in
 * 2026_08_11_120001 forces a full table rebuild, which regenerates the unique
 * index from the schema definition and silently drops that WHERE clause — so a
 * regenerated batch step (change control #182 soft-deletes the not-started steps
 * and recreates them with the same step_number) hits a UNIQUE violation.
 * PostgreSQL keeps the partial index across ADD COLUMN, so this is a no-op there;
 * the logic is idempotent (skips indexes that are already partial) regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->upPostgres(),
            'sqlite' => $this->upSqlite(),
            default => null, // unsupported driver: leave indexes as-is
        };
    }

    public function down(): void
    {
        // Non-destructive corrective migration; the partial index is the intended
        // state established in 2026_06_15_000002, so there is nothing to roll back.
    }

    private function upPostgres(): void
    {
        $indexes = DB::select(<<<'SQL'
            SELECT i.indexname, i.indexdef, (c.conname IS NOT NULL) AS is_constraint
            FROM pg_indexes i
            LEFT JOIN pg_constraint c ON c.conname = i.indexname AND c.contype = 'u'
            WHERE i.schemaname = current_schema()
              AND i.tablename = 'batch_steps'
              AND i.indexdef LIKE 'CREATE UNIQUE INDEX%'
              AND i.indexname NOT LIKE '%_pkey'
            SQL);

        foreach ($indexes as $index) {
            if (stripos($index->indexdef, ' WHERE ') !== false) {
                continue; // already partial
            }

            if ($index->is_constraint) {
                DB::statement(sprintf('ALTER TABLE batch_steps DROP CONSTRAINT %s', $index->indexname));
            } else {
                DB::statement(sprintf('DROP INDEX %s', $index->indexname));
            }

            DB::statement($index->indexdef.' WHERE deleted_at IS NULL');
        }
    }

    private function upSqlite(): void
    {
        $indexes = DB::select(
            "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'batch_steps' AND sql LIKE 'CREATE UNIQUE INDEX%'",
        );

        foreach ($indexes as $index) {
            if (stripos($index->sql, ' where ') !== false) {
                continue; // already partial
            }

            DB::statement(sprintf('DROP INDEX "%s"', $index->name));
            DB::statement($index->sql.' where "deleted_at" is null');
        }
    }
};
