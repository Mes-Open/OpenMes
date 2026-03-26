<?php

namespace App\Contracts\Services;

use App\Models\WorkOrder;
use App\Models\Batch;

interface WorkOrderServiceInterface
{
    public function createWorkOrder(array $data): WorkOrder;
    public function updateWorkOrder(WorkOrder $workOrder, array $data): WorkOrder;
    public function createBatch(WorkOrder $workOrder, float $targetQty): Batch;
    public function updateWorkOrderStatus(WorkOrder $workOrder): void;
    public function getWorkOrdersForUser($user, array $filters = []);
}
