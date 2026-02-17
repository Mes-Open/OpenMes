<?php

namespace App\Events\WorkOrder;

use App\Models\WorkOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkOrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WorkOrder $workOrder
    ) {}
}
