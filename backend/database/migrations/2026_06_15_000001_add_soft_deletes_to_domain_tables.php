<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes with deletion audit across all user-deletable domain tables
 * (and their cascade children). deleted_by_id records who deleted the row.
 *
 * The list is intentionally a literal copy of App\Support\SoftDeleteRegistry
 * at the time this migration was written — do not point it at the registry.
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
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
                $t->foreignId('deleted_by_id')->nullable()
                    ->comment('User who soft-deleted the row')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('deleted_by_id');
                $t->dropSoftDeletes();
            });
        }
    }
};
