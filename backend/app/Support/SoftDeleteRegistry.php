<?php

namespace App\Support;

use App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * Single source of truth for which models are soft-deletable (trait
 * SoftDeletesWithAudit + deleted_at/deleted_by_id columns).
 *
 * Used by the admin Trash page (list + restore by type key) and by the sync
 * read path (CollectionController filters trashed rows out of snapshots).
 * The corresponding migration keeps its own literal copy of the table list —
 * migrations must not depend on code that can change after they ran.
 */
class SoftDeleteRegistry
{
    /** type key (= table name) => model class */
    public const MODELS = [
        // Config / lookup
        'anomaly_reasons' => Models\AnomalyReason::class,
        'scrap_reasons' => Models\ScrapReason::class,
        'cost_sources' => Models\CostSource::class,
        'wage_groups' => Models\WageGroup::class,
        'skills' => Models\Skill::class,
        'personnel_classes' => Models\PersonnelClass::class,
        'workstation_types' => Models\WorkstationType::class,
        'subassemblies' => Models\Subassembly::class,
        'issue_types' => Models\IssueType::class,
        'issue_actions' => Models\IssueAction::class,
        'line_statuses' => Models\LineStatus::class,
        'lot_sequences' => Models\LotSequence::class,
        'view_templates' => Models\ViewTemplate::class,
        'label_templates' => Models\LabelTemplate::class,
        'custom_field_definitions' => Models\CustomFieldDefinition::class,
        'integration_configs' => Models\IntegrationConfig::class,
        'csv_import_mappings' => Models\CsvImportMapping::class,
        'shifts' => Models\Shift::class,
        'inspection_plans' => Models\InspectionPlan::class,
        'priority_rules' => Models\PriorityRule::class,
        'product_revisions' => Models\ProductRevision::class,

        // Structure
        'customers' => Models\Customer::class,
        'companies' => Models\Company::class,
        'sites' => Models\Site::class,
        'areas' => Models\Area::class,
        'factories' => Models\Factory::class,
        'divisions' => Models\Division::class,
        'crews' => Models\Crew::class,
        'crew_break_windows' => Models\CrewBreakWindow::class,

        // HR
        'workers' => Models\Worker::class,
        'worker_absences' => Models\WorkerAbsence::class,
        'employee_activities' => Models\EmployeeActivity::class,
        'users' => Models\User::class,

        // Production configuration
        'product_types' => Models\ProductType::class,
        'process_templates' => Models\ProcessTemplate::class,
        'template_steps' => Models\TemplateStep::class,
        'template_step_media' => Models\TemplateStepMedia::class,
        'template_step_checklist_items' => Models\TemplateStepChecklistItem::class,
        'batch_step_checklist_completions' => Models\BatchStepChecklistCompletion::class,
        'batch_step_documents' => Models\BatchStepDocument::class,
        'bom_items' => Models\BomItem::class,
        'quality_check_templates' => Models\QualityCheckTemplate::class,
        'quality_control_triggers' => Models\QualityControlTrigger::class,
        'quality_control_tasks' => Models\QualityControlTask::class,
        'process_template_photos' => Models\ProcessTemplatePhoto::class,
        'process_segments' => Models\ProcessSegment::class,
        'materials' => Models\Material::class,
        'material_lots' => Models\MaterialLot::class,
        'material_sublots' => Models\MaterialSublot::class,
        'lines' => Models\Line::class,
        'workstations' => Models\Workstation::class,
        'workstation_devices' => Models\WorkstationDevice::class,
        'line_view_columns' => Models\LineViewColumn::class,

        // Production data
        'work_orders' => Models\WorkOrder::class,
        'work_order_eans' => Models\WorkOrderEan::class,
        'batches' => Models\Batch::class,
        'batch_steps' => Models\BatchStep::class,
        'additional_costs' => Models\AdditionalCost::class,
        'scrap_entries' => Models\ScrapEntry::class,
        'work_order_shift_entries' => Models\WorkOrderShiftEntry::class,
        'issues' => Models\Issue::class,
        'pallets' => Models\Pallet::class,
        'attachments' => Models\Attachment::class,

        // Maintenance
        'tools' => Models\Tool::class,
        'maintenance_events' => Models\MaintenanceEvent::class,
        'maintenance_schedules' => Models\MaintenanceSchedule::class,
        'production_anomalies' => Models\ProductionAnomaly::class,

        // Connectivity
        'machine_connections' => Models\MachineConnection::class,
        'modbus_connections' => Models\ModbusConnection::class,
        'mqtt_connections' => Models\MqttConnection::class,
        'opcua_connections' => Models\OpcuaConnection::class,
        'machine_topics' => Models\MachineTopic::class,
        'topic_mappings' => Models\TopicMapping::class,
        'machine_tags' => Models\MachineTag::class,

        // Integrations
        'webhooks' => Models\Webhook::class,
        'api_keys' => Models\ApiKey::class,
    ];

    /** Attributes tried (in order) to label a trashed row in the Trash UI. */
    private const LABEL_ATTRIBUTES = [
        'name', 'title', 'lot_number', 'order_no', 'pallet_no', 'ean',
        'serial_no', 'code', 'email', 'topic', 'key',
    ];

    /** @return list<string> */
    public static function tables(): array
    {
        return array_keys(self::MODELS);
    }

    /** @return class-string|null */
    public static function modelFor(string $type): ?string
    {
        return self::MODELS[$type] ?? null;
    }

    public static function isSoftDeletable(string $table): bool
    {
        return array_key_exists($table, self::MODELS);
    }

    /** Human-readable identifier of a trashed row for the Trash listing. */
    public static function labelFor(Model $model): string
    {
        foreach (self::LABEL_ATTRIBUTES as $attribute) {
            $value = $model->getAttribute($attribute);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '#'.$model->getKey();
    }
}
