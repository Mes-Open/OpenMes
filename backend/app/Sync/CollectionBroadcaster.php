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
            'process_segments' => [Models\ProcessSegment::class, null],
            'view_templates' => [Models\ViewTemplate::class, null],
            'inspection_plans' => [Models\InspectionPlan::class, null],
            'integration_configs' => [Models\IntegrationConfig::class, null],
            'label_templates' => [Models\LabelTemplate::class, null],
            'maintenance_events' => [Models\MaintenanceEvent::class, null],
            'maintenance_schedules' => [Models\MaintenanceSchedule::class, null],
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

    /**
     * Dispatch a CollectionChanged delta without ever letting a broadcast
     * failure break the originating write. Live sync is best-effort: if the
     * broadcaster (e.g. Reverb) is unreachable, the write must still succeed
     * and clients fall back to polling (useSyncedShape). Failures are logged.
     */
    private static function safeBroadcast(string $name, string $op, array $row, $tenant): void
    {
        try {
            event(new CollectionChanged($name, $op, $row, $tenant));
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
            foreach (['created', 'updated', 'deleted'] as $event) {
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
