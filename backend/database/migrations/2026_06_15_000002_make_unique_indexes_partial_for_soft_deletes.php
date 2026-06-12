<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convert every UNIQUE index on the soft-deletable tables into a partial
 * unique index (WHERE deleted_at IS NULL). Without this, a soft-deleted row
 * still occupies its unique value at the DB level, so re-creating an entity
 * with the same code/number would fail even though validation allows it.
 *
 * Generic by introspection: works on PostgreSQL (production) and SQLite
 * (test suite). Same literal table list as the previous migration.
 */
return new class extends Migration
{
    private const TABLES = [
        'anomaly_reasons', 'scrap_reasons', 'cost_sources', 'wage_groups',
        'skills', 'personnel_classes', 'workstation_types', 'subassemblies',
        'issue_types', 'line_statuses', 'lot_sequences', 'view_templates',
        'label_templates', 'custom_field_definitions', 'integration_configs',
        'csv_import_mappings', 'shifts', 'inspection_plans',
        'companies', 'sites', 'areas', 'factories', 'divisions', 'crews',
        'crew_break_windows',
        'workers', 'worker_absences', 'employee_activities', 'users',
        'product_types', 'process_templates', 'template_steps', 'bom_items',
        'quality_check_templates', 'process_template_photos',
        'process_segments', 'materials', 'material_lots', 'material_sublots',
        'lines', 'workstations', 'line_view_columns',
        'work_orders', 'work_order_eans', 'batches', 'batch_steps',
        'additional_costs', 'scrap_entries', 'work_order_shift_entries',
        'issues', 'pallets', 'attachments',
        'tools', 'maintenance_events', 'maintenance_schedules',
        'production_anomalies',
        'machine_connections', 'modbus_connections', 'mqtt_connections',
        'opcua_connections', 'machine_topics', 'topic_mappings', 'machine_tags',
    ];

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
        match (DB::getDriverName()) {
            'pgsql' => $this->downPostgres(),
            'sqlite' => $this->downSqlite(),
            default => null,
        };
    }

    private function upPostgres(): void
    {
        foreach (self::TABLES as $table) {
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
                    continue; // already partial
                }

                if ($index->is_constraint) {
                    DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $table, $index->indexname));
                } else {
                    DB::statement(sprintf('DROP INDEX %s', $index->indexname));
                }

                DB::statement($index->indexdef.' WHERE deleted_at IS NULL');
            }
        }
    }

    private function downPostgres(): void
    {
        foreach (self::TABLES as $table) {
            $indexes = DB::select(<<<'SQL'
                SELECT indexname, indexdef
                FROM pg_indexes
                WHERE schemaname = current_schema()
                  AND tablename = ?
                  AND indexdef LIKE 'CREATE UNIQUE INDEX%'
                  AND indexdef LIKE '%deleted_at IS NULL%'
                SQL, [$table]);

            foreach ($indexes as $index) {
                DB::statement(sprintf('DROP INDEX %s', $index->indexname));
                DB::statement(preg_replace('/\s+WHERE\s+.*$/i', '', $index->indexdef));
            }
        }
    }

    private function upSqlite(): void
    {
        foreach (self::TABLES as $table) {
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
    }

    private function downSqlite(): void
    {
        foreach (self::TABLES as $table) {
            $indexes = DB::select(
                "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql LIKE 'CREATE UNIQUE INDEX%' AND sql LIKE '%deleted_at%'",
                [$table],
            );

            foreach ($indexes as $index) {
                DB::statement(sprintf('DROP INDEX "%s"', $index->name));
                DB::statement(preg_replace('/\s+where\s+.*$/i', '', $index->sql));
            }
        }
    }
};
