<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = App\Models\BatchStep::find(1);
$user = App\Models\User::first();

try {
    app(App\Services\WorkOrder\BatchService::class)->startStep($step, $user);
    echo "SUCCESS!\n";
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . "\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
