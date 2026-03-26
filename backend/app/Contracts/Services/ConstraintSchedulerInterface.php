<?php

namespace App\Contracts\Services;

use App\Models\WorkOrder;

interface ConstraintSchedulerInterface
{
    public function canSchedule(WorkOrder $wo): array;
}
