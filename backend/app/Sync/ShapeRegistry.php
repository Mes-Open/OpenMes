<?php

namespace App\Sync;

use App\Sync\Shapes\IssuesOpenShape;
use App\Sync\Shapes\IssueTypesShape;
use App\Sync\Shapes\LinesActiveShape;
use App\Sync\Shapes\OeeRecordsRecentShape;
use App\Sync\Shapes\ProductTypesShape;
use App\Sync\Shapes\WorkOrdersActiveShape;

/**
 * Whitelist of shapes the HTTP proxy will serve. Clients request a shape by
 * the keys in this array — never by table name — so adding sync coverage for
 * a new table is a deliberate, code-reviewed action.
 *
 * A value is either a Shape class-string (for shapes with real where() logic)
 * or an inline ['table','columns','where'?] config (wrapped in GenericShape) —
 * the latter keeps simple admin lookup tables to one line each.
 */
class ShapeRegistry
{
    protected array $shapes = [
        // Shapes with non-trivial scoping live in dedicated classes.
        'work_orders_active' => WorkOrdersActiveShape::class,
        'issues_open' => IssuesOpenShape::class,
        'oee_records_recent' => OeeRecordsRecentShape::class,
        'lines_active' => LinesActiveShape::class,
        'issue_types' => IssueTypesShape::class,
        'product_types' => ProductTypesShape::class,

        // Simple admin lookup tables — inline config.
        'skills' => [
            'table' => 'skills',
            'columns' => ['id', 'code', 'name', 'description', 'created_at', 'updated_at'],
        ],
        'anomaly_reasons' => [
            'table' => 'anomaly_reasons',
            'columns' => ['id', 'code', 'name', 'category', 'description', 'is_active', 'created_at', 'updated_at'],
        ],
        'scrap_reasons' => [
            'table' => 'scrap_reasons',
            'columns' => ['id', 'code', 'name', 'category', 'description', 'is_active', 'sort_order', 'created_at', 'updated_at'],
        ],
        'companies' => [
            'table' => 'companies',
            'columns' => ['id', 'code', 'name', 'tax_id', 'type', 'email', 'phone', 'address', 'is_active', 'created_at', 'updated_at'],
        ],
        'customers' => [
            'table' => 'customers',
            'columns' => ['id', 'name', 'code', 'tier', 'payment_score', 'total_orders', 'total_revenue', 'notes', 'is_active', 'created_at', 'updated_at'],
        ],
        'priority_rules' => [
            'table' => 'priority_rules',
            'columns' => ['id', 'name', 'field_source', 'condition_type', 'condition_value', 'condition_value_max', 'points', 'is_active', 'sort_order', 'created_at', 'updated_at'],
        ],
        'cost_sources' => [
            'table' => 'cost_sources',
            'columns' => ['id', 'code', 'name', 'description', 'unit_cost', 'unit', 'currency', 'is_active', 'created_at', 'updated_at'],
        ],
        'wage_groups' => [
            'table' => 'wage_groups',
            'columns' => ['id', 'code', 'name', 'description', 'base_hourly_rate', 'currency', 'is_active', 'created_at', 'updated_at'],
        ],
        'worker_absences' => [
            'table' => 'worker_absences',
            'columns' => ['id', 'worker_id', 'type', 'starts_on', 'ends_on', 'all_day', 'start_time', 'end_time', 'status', 'reason', 'created_by_id', 'created_at', 'updated_at'],
        ],
        'crew_break_windows' => [
            'table' => 'crew_break_windows',
            'columns' => ['id', 'crew_id', 'name', 'start_time', 'end_time', 'days_of_week', 'is_active', 'created_at', 'updated_at'],
        ],
        'factories' => [
            'table' => 'factories',
            'columns' => ['id', 'code', 'name', 'description', 'is_active', 'created_at', 'updated_at'],
        ],
        'divisions' => [
            'table' => 'divisions',
            'columns' => ['id', 'factory_id', 'code', 'name', 'description', 'is_active', 'created_at', 'updated_at'],
        ],
        'areas' => [
            'table' => 'areas',
            'columns' => ['id', 'site_id', 'code', 'name', 'description', 'is_active', 'custom_fields', 'created_at', 'updated_at'],
        ],
        'sites' => [
            'table' => 'sites',
            'columns' => ['id', 'company_id', 'code', 'name', 'description', 'address', 'city', 'country', 'timezone', 'is_active', 'custom_fields', 'created_at', 'updated_at'],
        ],
        'crews' => [
            'table' => 'crews',
            'columns' => ['id', 'code', 'name', 'leader_id', 'division_id', 'description', 'is_active', 'created_at', 'updated_at'],
        ],
        'tools' => [
            'table' => 'tools',
            'columns' => ['id', 'code', 'name', 'description', 'workstation_type_id', 'status', 'next_service_at', 'custom_fields', 'created_at', 'updated_at'],
        ],
        // Global line statuses only (per-line statuses are managed elsewhere).
        'line_statuses_global' => [
            'table' => 'line_statuses',
            'columns' => ['id', 'name', 'color', 'sort_order', 'line_id', 'is_default', 'is_done_status', 'created_at', 'updated_at'],
            'where' => 'line_id IS NULL',
        ],
        'personnel_classes' => [
            'table' => 'personnel_classes',
            'columns' => ['id', 'code', 'name', 'description', 'required_skill_ids', 'default_required_cert_level', 'is_active', 'created_at', 'updated_at'],
        ],
        'workstation_types' => [
            'table' => 'workstation_types',
            'columns' => ['id', 'code', 'name', 'description', 'is_active', 'created_at', 'updated_at'],
        ],
        'subassemblies' => [
            'table' => 'subassemblies',
            'columns' => ['id', 'code', 'name', 'description', 'product_type_id', 'is_active', 'created_at', 'updated_at'],
        ],
        'shifts' => [
            'table' => 'shifts',
            'columns' => ['id', 'code', 'name', 'start_time', 'end_time', 'sort_order', 'line_id', 'is_active', 'custom_fields', 'created_at', 'updated_at'],
        ],
        'issue_types_all' => [
            'table' => 'issue_types',
            'columns' => ['id', 'code', 'name', 'severity', 'is_blocking', 'is_active', 'created_at', 'updated_at'],
        ],
        // Shop-floor workstation clients — the MAIN roster derives online/offline
        // from last_seen_at. Admin list page (Admin -> Workstation devices).
        'workstation_devices' => [
            'table' => 'workstation_devices',
            'columns' => ['id', 'device_uuid', 'name', 'ip_address', 'hostname', 'app_version', 'line_id', 'last_seen_at', 'registered_at', 'created_at', 'updated_at'],
        ],
        // Quality-control trigger config (#105) — admin list.
        'quality_control_triggers' => [
            'table' => 'quality_control_triggers',
            'columns' => [
                'id', 'name', 'trigger_type', 'quality_check_template_id', 'line_id', 'workstation_id',
                'product_type_id', 'threshold_n', 'downtime_min_minutes', 'is_blocking', 'is_active',
                'created_at', 'updated_at',
            ],
        ],
        // Outstanding quality controls (#105) — the operator/supervisor "due" queue.
        'quality_control_tasks_due' => [
            'table' => 'quality_control_tasks',
            'columns' => [
                'id', 'quality_control_trigger_id', 'status', 'work_order_id', 'batch_id', 'workstation_id',
                'line_id', 'due_reason', 'quality_check_id', 'issue_id', 'fired_at', 'created_at', 'updated_at',
            ],
            'where' => "status IN ('due', 'in_progress')",
        ],
        'materials' => [
            'table' => 'materials',
            'columns' => ['id', 'code', 'name', 'description', 'material_type_id', 'unit_of_measure', 'tracking_type', 'default_scrap_percentage', 'external_code', 'external_system', 'stock_quantity', 'is_active', 'custom_fields', 'created_at', 'updated_at'],
        ],

        // Admin-defined custom-field schema. Tenant-scoped (global defs on "g").
        // NOTE: `config` is a JSON column — the snapshot (DB::table, no Eloquent
        // casts) serves it as a raw JSON string while live deltas send a decoded
        // array, so a client consuming this collection must JSON.parse the
        // snapshot value. The same caveat applies to every `custom_fields`
        // column added to the entity shapes below.
        'custom_field_definitions' => [
            'table' => 'custom_field_definitions',
            'columns' => ['id', 'entity_type', 'key', 'label', 'type', 'config', 'required', 'position', 'is_active', 'tenant_id', 'created_at', 'updated_at'],
        ],
        // Users: SAFE columns only — never sync password/pin/remember_token.
        'users' => [
            'table' => 'users',
            'columns' => ['id', 'name', 'username', 'email', 'account_type', 'force_password_change', 'last_login_at', 'worker_id', 'workstation_id', 'created_at', 'updated_at'],
        ],
        'lot_sequences' => [
            'table' => 'lot_sequences',
            'columns' => ['id', 'name', 'product_type_id', 'prefix', 'suffix', 'pattern', 'next_number', 'pad_size', 'year_prefix', 'reset_period', 'created_at', 'updated_at'],
        ],
        'pallets' => [
            'table' => 'pallets',
            'columns' => ['id', 'pallet_no', 'work_order_id', 'batch_id', 'qty', 'status', 'quality_status', 'location', 'erp_reference', 'created_at', 'updated_at'],
        ],
        // integration_configs: exclude api_config (may hold credentials).
        'integration_configs' => [
            'table' => 'integration_configs',
            'columns' => ['id', 'system_type', 'system_name', 'is_active', 'created_at', 'updated_at'],
        ],
        'workers' => [
            'table' => 'workers',
            'columns' => ['id', 'code', 'name', 'email', 'phone', 'crew_id', 'wage_group_id', 'personnel_class_id', 'workstation_id', 'is_active', 'custom_fields', 'created_at', 'updated_at'],
        ],
        'process_segments' => [
            'table' => 'process_segments',
            'columns' => ['id', 'code', 'name', 'description', 'segment_type', 'workstation_type_id', 'estimated_duration_minutes', 'required_operators', 'required_skill_ids', 'is_active', 'created_at', 'updated_at'],
        ],
        // All work orders (incl. terminal) for the admin list — the dashboard's
        // work_orders_active excludes done/cancelled/rejected.
        'work_orders_all' => [
            'table' => 'work_orders',
            'columns' => ['id', 'order_no', 'customer_order_no', 'customer_id', 'line_id', 'product_type_id', 'planned_qty', 'unit_price', 'produced_qty', 'counting_source', 'status', 'priority', 'priority_score', 'due_date', 'completed_at', 'custom_fields', 'created_at', 'updated_at'],
        ],
        // All lines (incl. inactive) for the admin list — lines_active is active-only.
        'lines_all' => [
            'table' => 'lines',
            'columns' => ['id', 'code', 'name', 'description', 'is_active', 'area_id', 'division_id', 'view_template_id', 'default_operator_view', 'custom_fields', 'created_at', 'updated_at'],
        ],
        'maintenance_events' => [
            'table' => 'maintenance_events',
            'columns' => ['id', 'title', 'event_type', 'status', 'tool_id', 'line_id', 'workstation_id', 'cost_source_id', 'assigned_to_id', 'scheduled_at', 'scheduled_end_at', 'actual_cost', 'currency', 'created_at', 'updated_at'],
        ],
        'maintenance_schedules' => [
            'table' => 'maintenance_schedules',
            'columns' => ['id', 'name', 'tool_id', 'line_id', 'workstation_id', 'event_type', 'assigned_to_id', 'cost_source_id', 'frequency', 'interval_value', 'preferred_time', 'lead_time_days', 'next_due_at', 'is_active', 'created_at', 'updated_at'],
        ],
        'material_lots' => [
            'table' => 'material_lots',
            'columns' => ['id', 'lot_number', 'material_id', 'source_id', 'quantity_received', 'quantity_available', 'unit_of_measure', 'received_at', 'manufacturing_date', 'expiry_date', 'status', 'supplier_lot_no', 'supplier_reference', 'source_container_no', 'issue_id', 'hold_reason', 'held_at', 'held_by_id', 'released_at', 'released_by_id', 'created_at', 'updated_at'],
        ],
        'view_templates' => [
            'table' => 'view_templates',
            'columns' => ['id', 'name', 'description', 'created_at', 'updated_at'],
        ],
        'inspection_plans' => [
            'table' => 'inspection_plans',
            'columns' => ['id', 'name', 'description', 'material_id', 'material_type_id', 'is_active', 'version', 'published_at', 'root_id', 'created_at', 'updated_at'],
        ],
        'label_templates' => [
            'table' => 'label_templates',
            'columns' => ['id', 'name', 'type', 'size', 'barcode_format', 'is_default', 'is_active', 'created_at', 'updated_at'],
        ],
        // All issues (any status) for the supervisor/admin issue management page.
        'issues_all' => [
            'table' => 'issues',
            'columns' => ['id', 'work_order_id', 'issue_type_id', 'title', 'description', 'status', 'disposition', 'non_conforming_qty', 'root_cause', 'containment_action', 'nc_source', 'disposition_at', 'reported_by_id', 'assigned_to_id', 'reported_at', 'acknowledged_at', 'resolved_at', 'closed_at', 'material_id', 'source', 'custom_fields', 'created_at', 'updated_at'],
        ],
        'issue_actions' => [
            'table' => 'issue_actions',
            'columns' => ['id', 'issue_id', 'type', 'title', 'description', 'status', 'assigned_to_id', 'due_date', 'completed_at', 'completed_by_id', 'verified_at', 'verified_by_id', 'notes', 'created_at', 'updated_at'],
        ],
        // Outgoing webhooks (#20). NEVER sync `secret` — the HMAC signing key
        // must not leave the server.
        'webhooks' => [
            'table' => 'webhooks',
            'columns' => ['id', 'name', 'url', 'events', 'is_active', 'last_triggered_at', 'created_at', 'updated_at'],
        ],
        // Delivery log — payload/response_body are excluded to keep the stream
        // light; the status/code/error are enough for the admin list.
        'webhook_deliveries' => [
            'table' => 'webhook_deliveries',
            'columns' => ['id', 'webhook_id', 'event_type', 'status', 'attempts', 'response_code', 'error', 'delivered_at', 'created_at', 'updated_at'],
        ],
    ];

    public function find(string $name): ?Shape
    {
        $def = $this->shapes[$name] ?? null;

        if ($def === null) {
            return null;
        }

        if (is_array($def)) {
            return new GenericShape($def['table'], $def['columns'], $def['where'] ?? null);
        }

        return new $def;
    }
}
