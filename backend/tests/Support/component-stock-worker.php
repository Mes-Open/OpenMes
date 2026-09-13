<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_contains(\Illuminate\Support\Facades\DB::connection()->getDatabaseName(), '_test')) {
    throw new RuntimeException('Only run against a disposable test database.');
}
app(\App\Services\WorkOrder\ComponentWorkOrderService::class)->generate(\App\Models\WorkOrder::findOrFail((int) $argv[1]), [
    'use_component_stock' => true, 'component_warehouse_ids' => [(int) $argv[2]],
]);
