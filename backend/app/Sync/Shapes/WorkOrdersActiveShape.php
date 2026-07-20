<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Models\WorkOrder;
use App\Sync\Shape;

/**
 * Work orders on active lines that are not in a terminal state.
 *
 * Used as the vertical-slice proof of Electric live sync. Once we trust the
 * loop, this is the shape the admin/supervisor dashboards will subscribe to
 * for the "active production" view.
 */
class WorkOrdersActiveShape extends Shape
{
    public function table(): string
    {
        return 'work_orders';
    }

    public function columns(): array
    {
        return [
            'id',
            'order_no',
            'customer_order_no',
            'customer_id',
            'line_id',
            'product_type_id',
            'status',
            'priority',
            'priority_score',
            'planned_qty',
            'unit_price',
            'produced_qty',
            'counting_source',
            'due_date',
            'completed_at',
            'planned_start_at',
            'planned_end_at',
            'custom_fields',
            'created_at',
            'updated_at',
        ];
    }

    public function where(User $user): ?string
    {
        // Open work orders only — terminal statuses don't change anymore and
        // clients don't need them in the live shape. Status values are the
        // App\Models\WorkOrder::STATUS_* uppercase constants.
        $terminal = collect(WorkOrder::TERMINAL_STATUSES)
            ->map(fn ($s) => "'{$s}'")
            ->implode(',');

        return "status NOT IN ({$terminal})";
    }
}
