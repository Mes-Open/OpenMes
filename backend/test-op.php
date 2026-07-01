<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = App\Models\BatchStep::where('status', 'PENDING')->first();
$user = App\Models\User::where('account_type', 'operator')->orderBy('id', 'desc')->first();

echo "Step: " . $step->id . " - " . $step->templateStep->name . "\n";
echo "User: " . $user->name . " (Workstation: " . ($user->workstation_id ?: 'null') . ")\n";

try {
    app(App\Services\WorkOrder\BatchService::class)->startStep($step, $user);
    echo "SUCCESS!\n";
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . "\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
