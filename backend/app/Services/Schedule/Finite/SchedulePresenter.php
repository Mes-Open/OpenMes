<?php

namespace App\Services\Schedule\Finite;

use App\Models\ScheduledOperation;
use App\Models\ScheduleSimulation;

/**
 * The one JSON shape of scheduled operations and simulations, shared by the
 * planner page and the API — so the completion forecast (#241) and schedule
 * suggestions (#39) read exactly what a planner sees.
 *
 * Timestamps are ISO-8601; `*_label` fields are the same instant formatted in
 * plant time for display.
 */
class SchedulePresenter
{
    /** What {@see operation()} reads; eager-load it on every query it formats. */
    public const RELATIONS = [
        'workOrder:id,order_no,status,product_type_id,planned_qty,due_date',
        'workOrder.productType:id,name,unit_of_measure',
        'line:id,name',
        'workstation:id,name',
    ];

    /** @return array<string, mixed> */
    public function operation(ScheduledOperation $op): array
    {
        return [
            'id' => $op->id,
            'simulation_id' => $op->schedule_simulation_id,
            'work_order_id' => $op->work_order_id,
            'order_no' => $op->workOrder?->order_no,
            'product_name' => $op->workOrder?->productType?->name,
            'planned_qty' => $op->workOrder ? (float) $op->workOrder->planned_qty : null,
            'unit' => $op->workOrder?->productType?->unit_of_measure,
            'due_date' => $op->workOrder?->due_date?->toDateString(),
            'batch_step_id' => $op->batch_step_id,
            'step_number' => $op->step_number,
            'step_name' => $op->step_name,
            'sequence' => $op->sequence,
            'line_id' => $op->line_id,
            'line_name' => $op->line?->name,
            'workstation_id' => $op->workstation_id,
            'workstation_name' => $op->workstation?->name,
            'workstation_type_id' => $op->workstation_type_id,
            'resource_source' => $op->resource_source,
            'planned_start_at' => $op->planned_start_at?->toIso8601String(),
            'planned_end_at' => $op->planned_end_at?->toIso8601String(),
            'planned_start_label' => $op->planned_start_at?->format('Y-m-d H:i'),
            'planned_end_label' => $op->planned_end_at?->format('Y-m-d H:i'),
            'duration_minutes' => $op->duration_minutes,
            'setup_minutes' => $op->setup_minutes,
            'required_operators' => $op->required_operators,
            'status' => $op->status->value,
            'status_label' => $op->status->label(),
            'scheduled' => $op->status->isScheduled(),
            'reasons' => $op->reasons ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function simulation(ScheduleSimulation $simulation, bool $withOperations = true): array
    {
        $out = [
            'id' => $simulation->id,
            'status' => $simulation->status,
            'horizon_start' => $simulation->horizon_start?->toIso8601String(),
            'horizon_end' => $simulation->horizon_end?->toIso8601String(),
            'horizon_label' => $simulation->horizon_start?->format('Y-m-d H:i').' – '.$simulation->horizon_end?->format('Y-m-d H:i'),
            'parameters' => $simulation->parameters,
            'counts' => $simulation->summary['counts'] ?? [],
            'work_orders' => $simulation->summary['work_orders'] ?? [],
            'resources' => $simulation->summary['resources'] ?? [],
            'workforce' => $simulation->summary['workforce'] ?? [],
            // Drafts from before the per-shift breakdown have none.
            'staffing' => $simulation->summary['staffing'] ?? null,
            'created_at' => $simulation->created_at?->toIso8601String(),
            'created_label' => $simulation->created_at?->format('Y-m-d H:i'),
            'created_by' => $simulation->createdBy?->name,
            'applied_at' => $simulation->applied_at?->toIso8601String(),
        ];

        if ($withOperations) {
            $out['operations'] = $simulation->operations()
                ->with([...self::RELATIONS])
                ->get()
                ->map(fn (ScheduledOperation $op) => $this->operation($op))
                ->all();
        }

        return $out;
    }
}
