<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let the organisational and workforce lookup tables live outside core.
 *
 * A line records the area and division it sits in, and a worker records their
 * crew, wage group and personnel class. Those five lookups are plant
 * administration rather than production, and they now ship as an optional
 * module — but a foreign key from a core table pins them in place: an
 * installation without the module would fail at `migrate` on a constraint
 * pointing at a table that was never created.
 *
 * The COLUMNS stay. They keep whatever they hold, the module re-adds the
 * constraints when it creates the tables, and an installation that never
 * installs the module simply carries five unused nullable integers.
 *
 * Dropping a constraint is effectively one-way — putting it back requires every
 * existing value to still resolve — so this deliberately does nothing on the way
 * down rather than pretending to be reversible.
 */
return new class extends Migration
{
    /** table => columns whose foreign key is dropped */
    private const COLUMNS = [
        'lines' => ['area_id', 'division_id'],
        'workers' => ['crew_id', 'wage_group_id', 'personnel_class_id'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                // SQLite has no DROP CONSTRAINT; its foreign keys are part of the
                // table definition and Laravel's test databases are created fresh
                // from the migrations that follow, so there is nothing to undo.
                if (Schema::getConnection()->getDriverName() === 'sqlite') {
                    continue;
                }

                // Ask first rather than drop-and-catch. Two reasons the guard
                // has to work this way:
                //
                //  - A catch *inside* the Schema::table closure never fires:
                //    the closure only records the command on the Blueprint, and
                //    the SQL runs after it returns.
                //  - Catching it outside is no better on PostgreSQL, which
                //    aborts the surrounding transaction on any failed statement
                //    — every later statement in the migration then dies with
                //    "current transaction is aborted" regardless of the catch.
                //
                // So the "already gone" case — an installation that never had
                // the constraint, or a re-run — took the whole upgrade down.
                if (! $this->hasForeignKeyOn($table, $column)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
            }
        }
    }

    /**
     * Whether $table still carries a foreign key whose columns are exactly
     * [$column]. Read from the live schema, so it is true only for databases
     * that actually have the constraint to drop.
     */
    private function hasForeignKeyOn(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (($foreignKey['columns'] ?? []) === [$column]) {
                return true;
            }
        }

        return false;
    }

    public function down(): void {}
};
