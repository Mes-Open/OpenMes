<?php

namespace App\Events\Schedule;

use App\Models\WorkOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a work order's schedule placement changes on the planner — assigned
 * to a line, moved to another day/shift, given a minute-level window, or
 * unassigned. `changes` is the set of placement fields that were written
 * (line_id, due_date, shift_number, end_date, planned_start_at, …).
 *
 * Lets a module react to planning decisions (notify, sync a calendar, push to an
 * ERP) without polling the schedule.
 */
class WorkOrderScheduled
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $changes  Placement fields written by the planner edit.
     */
    public function __construct(
        public WorkOrder $workOrder,
        public array $changes,
    ) {}
}
