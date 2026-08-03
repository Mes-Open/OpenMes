<?php

namespace Modules\OrderPinger;

use App\Events\Resource\ResourceChanged;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class Hooks
{
    // Typed hook: fires only for work orders.
    public function onWorkOrderCreated(WorkOrderCreated $e): void
    {
        Log::channel('daily')->info('[OrderPinger] order created', [
            'order_no' => $e->workOrder->order_no,
        ]);
    }

    // Generic CRUD hook: fires for ANY curated resource. Filter what you want.
    public function onResourceChanged(ResourceChanged $e): void
    {
        if ($e->model instanceof Customer && $e->action === 'created') {
            Log::channel('daily')->info('[OrderPinger] new customer', ['id' => $e->model->id]);
        }
    }
}
