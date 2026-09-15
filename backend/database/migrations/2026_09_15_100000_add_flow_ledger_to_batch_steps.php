<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-step quantity ledger for transfer-flow production: pieces move to the
 * next station as soon as they leave this one, instead of waiting for the whole
 * batch. `passed_qty` (good pieces that left the station, already fed by
 * break-beam sensors) becomes a decimal so it lines up with every other
 * quantity column, and `scrap_qty` records what was lost at the step.
 * "Incoming" is not stored: it is the previous step's passed_qty, so steps
 * finished before this migration get their passed_qty backfilled.
 *
 * A quick quantity log records scrap as a bare number and the reason is added
 * later from the scrap report, so `scrap_entries.scrap_reason_id` can't stay
 * required.
 *
 * Both column changes rebuild the table on SQLite, which regenerates unique
 * indexes without their `WHERE deleted_at IS NULL` clause (see
 * 2026_08_11_120002) — so the partial indexes are re-asserted afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->decimal('passed_qty', 12, 2)->default(0)->change();
            $table->decimal('scrap_qty', 12, 2)->default(0)->after('passed_qty');
        });
        $this->restorePartialUniqueIndexes('batch_steps');
        $this->backfillLegacyPassedQty();

        Schema::table('scrap_entries', function (Blueprint $table) {
            $table->foreignId('scrap_reason_id')->nullable()->change();
        });
        $this->restorePartialUniqueIndexes('scrap_entries');
    }

    /**
     * Before the ledger, finishing a step moved the whole batch on but recorded
     * no quantity (passed_qty was only fed by break-beam sensors, if at all). Left
     * as is, every step after a finished one would see nothing incoming, so a
     * batch in flight when the installation switches to transfer flow could never
     * be finished. Treat each finished step as having passed its whole batch —
     * what the old flow meant — unless a sensor already counted more.
     */
    public function backfillLegacyPassedQty(): void
    {
        $batchTarget = '(SELECT target_qty FROM batches WHERE batches.id = batch_steps.batch_id)';

        DB::table('batch_steps')
            ->where('status', 'DONE')
            ->whereRaw("passed_qty < {$batchTarget}")
            ->update(['passed_qty' => DB::raw($batchTarget)]);
    }

    public function down(): void
    {
        // A schema downgrade cannot represent these production facts. Refuse
        // before any DDL (including NOT NULL) rather than lose quantities or
        // invent scrap classifications. Include soft-deleted audit records.
        // Run migrations with production writers stopped.
        $hasUnclassifiedScrap = DB::table('scrap_entries')->whereNull('scrap_reason_id')->exists();
        $hasLedgerData = DB::table('batch_steps')->where(function ($query) {
            $query->where('scrap_qty', '!=', 0)
                ->orWhereRaw('passed_qty != FLOOR(passed_qty)')
                ->orWhere('passed_qty', '<', 0)
                ->orWhere('passed_qty', '>', 2147483647);
        })->exists();
        if ($hasUnclassifiedScrap || $hasLedgerData) {
            throw new RuntimeException(
                'Cannot roll back the step quantity ledger without losing production data. '
                .'No schema changes were made. Keep this schema and roll forward, '
                .'or restore the pre-upgrade database backup with production writers stopped.'
            );
        }

        Schema::table('scrap_entries', function (Blueprint $table) {
            $table->foreignId('scrap_reason_id')->nullable(false)->change();
        });
        $this->restorePartialUniqueIndexes('scrap_entries');

        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropColumn('scrap_qty');
            $table->unsignedInteger('passed_qty')->default(0)->change();
        });
        $this->restorePartialUniqueIndexes('batch_steps');
    }

    /**
     * Make every plain unique index on the table partial on `deleted_at IS NULL`
     * again (idempotent: already-partial indexes are left alone). PostgreSQL
     * keeps partial indexes across ALTER COLUMN, so this is a no-op there.
     */
    private function restorePartialUniqueIndexes(string $table): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->restorePostgres($table),
            'sqlite' => $this->restoreSqlite($table),
            default => null,
        };
    }

    private function restorePostgres(string $table): void
    {
        $indexes = DB::select(<<<'SQL'
            SELECT i.indexname, i.indexdef, (c.conname IS NOT NULL) AS is_constraint
            FROM pg_indexes i
            LEFT JOIN pg_constraint c ON c.conname = i.indexname AND c.contype = 'u'
            WHERE i.schemaname = current_schema()
              AND i.tablename = ?
              AND i.indexdef LIKE 'CREATE UNIQUE INDEX%'
              AND i.indexname NOT LIKE '%_pkey'
            SQL, [$table]);

        foreach ($indexes as $index) {
            if (stripos($index->indexdef, ' WHERE ') !== false) {
                continue;
            }

            DB::statement($index->is_constraint
                ? sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $table, $index->indexname)
                : sprintf('DROP INDEX %s', $index->indexname));
            DB::statement($index->indexdef.' WHERE deleted_at IS NULL');
        }
    }

    private function restoreSqlite(string $table): void
    {
        $indexes = DB::select(
            "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql LIKE 'CREATE UNIQUE INDEX%'",
            [$table],
        );

        foreach ($indexes as $index) {
            if (stripos($index->sql, ' where ') !== false) {
                continue;
            }

            DB::statement(sprintf('DROP INDEX "%s"', $index->name));
            DB::statement($index->sql.' where "deleted_at" is null');
        }
    }
};
