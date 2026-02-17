<?php

namespace App\Events\WorkOrder;

use App\Models\WorkOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkOrderCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WorkOrder $workOrder
    ) {}
}
