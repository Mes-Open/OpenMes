<?php

namespace App\Sync;

use App\Events\CollectionChanged;
use App\Models;

/**
 * Central write-path for Reverb sync: maps each synced collection to its model
 * and (for filtered collections) a membership test, then registers model
 * create/update/delete listeners that broadcast a CollectionChanged delta.
 *
 * One place to see "model → collection(s)", instead of a trait on every model.
 * Booted from AppServiceProvider::boot(). Mirrors the read-side definitions in
 * ShapeRegistry (table/columns/where).
 *
 * Op semantics: "upsert" (client inserts or updates by key) or "delete". For a
 * filtered collection, a row that no longer matches its filter is broadcast as a
 * delete (it left the set) — e.g. a work order going terminal leaves
 * work_orders_active.
 */
class CollectionBroadcaster
{
    /**
     * collection name => [modelClass, membership(Model):bool|null]
     * null membership = always a member (unfiltered lookup table).
     *
     * @return array<string, array{0: class-string, 1: callable|null}>
     */
    private static function map(): array
    {
        $terminal = Models\WorkOrder::TERMINAL_STATUSES;
        $openIssue = [Models\Issue::STATUS_OPEN, Models\Issue::STATUS_ACKNOWLEDGED];

        return [
            // Filtered (shaped) collections — must drop rows that leave the set.
            'work_orders_active' => [Models\WorkOrder::class, fn ($m) => ! in_array($m->status, $terminal, true)],
            'work_orders_all' => [Models\WorkOrder::class, null],
            'issues_open' => [Models\Issue::class, fn ($m) => in_array($m->status, $openIssue, true)],
            'issues_all' => [Models\Issue::class, null],
            'lines_active' => [Models\Line::class, fn ($m) => (bool) $m->is_active],
            'lines_all' => [Models\Line::class, null],
            'issue_types' => [Models\IssueType::class, fn ($m) => (bool) $m->is_active],
            'issue_types_all' => [Models\IssueType::class, null],
            'oee_records_recent' => [Models\OeeRecord::class, fn ($m) => (string) $m->record_date >= now()->subDay()->toDateString()],
            'line_statuses_global' => [Models\LineStatus::class, fn ($m) => $m->line_id === null],

            // Unfiltered lookup / admin tables.
            'product_types' => [Models\ProductType::class, null],
            'skills' => [Models\Skill::class, null],
            'anomaly_reasons' => [Models\AnomalyReason::class, null],
            'companies' => [Models\Company::class, null],
            'cost_sources' => [Models\CostSource::class, null],
            'wage_groups' => [Models\WageGroup::class, null],
            'worker_absences' => [Models\WorkerAbsence::class, null],
            'crew_break_windows' => [Models\CrewBreakWindow::class, null],
            'factories' => [Models\Factory::class, null],
            'divisions' => [Models\Division::class, null],
            'areas' => [Models\Area::class, null],
            'sites' => [Models\Site::class, null],
            'crews' => [Models\Crew::class, null],
            'tools' => [Models\Tool::class, null],
            'personnel_classes' => [Models\PersonnelClass::class, null],
            'workstation_types' => [Models\WorkstationType::class, null],
            'subassemblies' => [Models\Subassembly::class, null],
            'shifts' => [Models\Shift::class, null],
            'users' => [Models\User::class, null],
            'workers' => [Models\Worker::class, null],
            'materials' => [Models\Material::class, null],
            'material_lots' => [Models\MaterialLot::class, null],
            'lot_sequences' => [Models\LotSequence::class, null],
            'pallets' => [Models\Pallet::class, null],
            'pallet_movements' => [Models\PalletMovement::class, null],
            'process_segments' => [Models\ProcessSegment::class, null],
            'view_templates' => [Models\ViewTemplate::class, null],
            'inspection_plans' => [Models\InspectionPlan::class, null],
            'integration_configs' => [Models\IntegrationConfig::class, null],
            'label_templates' => [Models\LabelTemplate::class, null],
            'maintenance_events' => [Models\MaintenanceEvent::class, null],
            'maintenance_schedules' => [Models\MaintenanceSchedule::class, null],
            'custom_field_definitions' => [Models\CustomFieldDefinition::class, null],
            'customers' => [Models\Customer::class, null],
            'issue_actions' => [Models\IssueAction::class, null],
            'priority_rules' => [Models\PriorityRule::class, null],
            'product_revisions' => [Models\ProductRevision::class, null],
            'quality_control_triggers' => [Models\QualityControlTrigger::class, null],
            'scrap_reasons' => [Models\ScrapReason::class, null],
            'webhooks' => [Models\Webhook::class, null],
            'webhook_deliveries' => [Models\WebhookDelivery::class, null],
            'workstation_devices' => [Models\WorkstationDevice::class, null],
            'quality_control_tasks_due' => [
                Models\QualityControlTask::class,
                fn ($m) => in_array($m->status, [
                    Models\QualityControlTask::STATUS_DUE,
                    Models\QualityControlTask::STATUS_IN_PROGRESS,
                ], true),
            ],

            // Change control (#182). A stop and its change request are what the
            // shop floor is waiting on, so both must reach open clients live.
            'work_order_stops' => [Models\WorkOrderStop::class, null],
            'work_order_change_requests' => [Models\WorkOrderChangeRequest::class, null],

            // Warehousing (#212). Balances change on every posted document and
            // ERP sync, so the stock overview must see them live.
            'warehouses' => [Models\Warehouse::class, null],
            'warehouse_stocks' => [Models\WarehouseStock::class, null],
            'stock_documents' => [Models\StockDocument::class, null],
        ];
    }

    /**
     * Manually broadcast a model's collection delta. Use after writes that
     * bypass Eloquent events — increment()/decrement() and query-builder mass
     * updates — so the change still reaches clients.
     */
    public static function flush($model): void
    {
        $class = get_class($model);
        $fresh = $model->fresh() ?? $model;
        $row = $fresh->attributesToArray();
        $tenant = $fresh->getAttribute('tenant_id');

        foreach (self::map() as $name => [$mclass, $member]) {
            if ($mclass !== $class) {
                continue;
            }
            $op = ($member !== null && ! $member($fresh)) ? 'delete' : 'upsert';
            self::safeBroadcast($name, $op, $row, $tenant);
        }
    }

    /** Column allowlist per collection, resolved once per request. */
    private static array $columnCache = [];

    /**
     * Narrow a row to the columns its collection actually exposes.
     *
     * The Shape's column list is an allowlist — "never include password hashes,
     * tokens, PII" — and it withholds real secrets: a webhook's custom auth
     * headers, a delivery's request/response bodies. The snapshot endpoint has
     * always honoured it, but the delta broadcast sent whatever
     * attributesToArray() returned, so a model attribute outside the list
     * reached every subscriber anyway. Project here so both paths expose the
     * same fields, which is what Shape's own docblock promises.
     */
    private static function project(string $name, array $row): array
    {
        if (! array_key_exists($name, self::$columnCache)) {
            self::$columnCache[$name] = app(ShapeRegistry::class)->find($name)?->columns();
        }

        $columns = self::$columnCache[$name];

        // No shape (a collection broadcast but not registered for reading) —
        // send nothing rather than guess at what is safe to expose.
        return $columns === null ? [] : array_intersect_key($row, array_flip($columns));
    }

    /**
     * Dispatch a CollectionChanged delta without ever letting a broadcast
     * failure break the originating write. Live sync is best-effort: if the
     * broadcaster (e.g. Reverb) is unreachable, the write must still succeed
     * and clients fall back to polling (useSyncedShape). Failures are logged.
     */
    private static function safeBroadcast(string $name, string $op, array $row, $tenant): void
    {
        try {
            event(new CollectionChanged($name, $op, self::project($name, $row), $tenant));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function boot(): void
    {
        // Group collections by model so each model registers its events once.
        $byModel = [];
        foreach (self::map() as $name => [$model, $member]) {
            if (class_exists($model)) {
                $byModel[$model][$name] = $member;
            }
        }

        foreach ($byModel as $model => $collections) {
            // `deleted` also fires on soft delete, broadcasting the row's removal.
            // `restored` (SoftDeletes models only) re-broadcasts it as an upsert.
            $events = ['created', 'updated', 'deleted'];
            if (method_exists($model, 'restored')) {
                $events[] = 'restored';
            }

            foreach ($events as $event) {
                $model::{$event}(function ($m) use ($collections, $event) {
                    $row = $m->attributesToArray();
                    $tenant = $m->getAttribute('tenant_id');

                    foreach ($collections as $name => $member) {
                        $op = ($event === 'deleted' || ($member !== null && ! $member($m))) ? 'delete' : 'upsert';
                        self::safeBroadcast($name, $op, $row, $tenant);
                    }
                });
            }
        }
    }
}
