<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$eansByWorkOrder = \App\Models\WorkOrderEan::select('work_order_id', 'ean')->get()->groupBy('work_order_id');
$items = \App\Models\WorkOrder::where(function ($q) {
    $q->whereIn('status', ['DONE', 'IN_PROGRESS'])->orWhere('produced_qty', '>', 0);
})->get()->filter(function($wo) use ($eansByWorkOrder) {
    return $eansByWorkOrder->has($wo->id);
})->values()->toArray();

echo json_encode($items, JSON_PRETTY_PRINT);
