<?php

/**
 * Generic fixture for operator-console-guards.spec.ts: one line with an
 * assembly bench and a packing bench, a product counted in pieces with a
 * two-step routing (assemble, then pack into cartons of 5), and the orders and
 * serial units the scenarios act on. Everything carries the run's suffix, so
 * runs never collide and nothing pre-existing is touched. No system setting is
 * changed: the spec reads the ones it depends on and skips when they differ.
 *
 * Run inside the app container:  RUN=123456 php < seed.php   (prints ids as JSON)
 * Clean up afterwards with:      RUN=123456 CLEANUP=1 php < seed.php
 */

use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\ScrapReason;
use App\Models\SerialUnit;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$base = getenv('OPENMES_APP_PATH') ?: '/var/www/html';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$run = getenv('RUN') ?: (string) time();
$tag = "E2E-G{$run}";

if (getenv('CLEANUP')) {
    // Soft deletes (Admin → Trash can bring them back); the accounts stay for the next run.
    WorkOrder::where('order_no', 'like', "{$tag}-%")->get()->each->delete();
    Line::where('code', 'like', "{$tag}%")->get()->each->delete();
    ProductType::where('code', 'like', "{$tag}-%")->get()->each->delete();
    echo json_encode(['cleaned' => $tag]), "\n";

    return;
}

$setting = fn (string $key, mixed $default) => json_decode(DB::table('system_settings')->where('key', $key)->value('value') ?? json_encode($default), true);

// Accounts shared by every run, created once. An existing account's password is never reset.
$password = getenv('E2E_PASSWORD') ?: 'E2e-Guards-2026!';
$account = function (string $username, string $name, string $role) use ($password) {
    $user = User::firstOrCreate(['username' => $username], ['name' => $name, 'email' => "{$username}@example.test", 'password' => Hash::make($password)]);
    $user->syncRoles([$role]);

    return $user;
};
$operator = $account('e2e-guards-op', 'Guards Operator', 'Operator');
$offLine = $account('e2e-guards-op-b', 'Guards Operator (no line)', 'Operator');
$supervisor = $account('e2e-guards-super', 'Guards Supervisor', 'Supervisor');
$admin = $account('e2e-guards-admin', 'Guards Admin', 'Admin');

$line = Line::create(['code' => $tag, 'name' => "Guards line {$run}", 'is_active' => true, 'default_operator_view' => 'queue']);
$otherLine = Line::create(['code' => "{$tag}-X", 'name' => "Guards other line {$run}", 'is_active' => true]);
$operator->lines()->syncWithoutDetaching([$line->id]);
$supervisor->lines()->syncWithoutDetaching([$line->id]);
$assembly = Workstation::create(['code' => "{$tag}-ASM", 'line_id' => $line->id, 'name' => 'Assembly bench', 'is_active' => true]);
$packing = Workstation::create(['code' => "{$tag}-PAK", 'line_id' => $line->id, 'name' => 'Packing bench', 'is_active' => true]);
Workstation::create(['code' => "{$tag}-X1", 'line_id' => $otherLine->id, 'name' => 'Other bench', 'is_active' => true]);

$product = ProductType::create(['code' => "{$tag}-W", 'name' => "Widget {$run}", 'unit_of_measure' => 'pcs', 'is_active' => true]);
$product->lines()->syncWithoutDetaching([$line->id, $otherLine->id]);
$template = ProcessTemplate::create(['product_type_id' => $product->id, 'name' => 'Widget routing', 'version' => 1, 'is_active' => true]);
TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Assemble', 'workstation_id' => $assembly->id, 'estimated_duration_minutes' => 5]);
TemplateStep::create([
    'process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Pack', 'workstation_id' => $packing->id,
    'kind' => TemplateStep::KIND_PACKING, 'config' => TemplateStep::normalisePackingConfig(['unit' => 'carton', 'carton_capacity' => 5, 'unit_label' => false]),
]);

$orders = app(WorkOrderService::class);
$main = $orders->createWorkOrder(['order_no' => "{$tag}-A", 'product_type_id' => $product->id, 'line_id' => $line->id, 'planned_qty' => 4]);
$cancelled = $orders->createWorkOrder(['order_no' => "{$tag}-C", 'product_type_id' => $product->id, 'line_id' => $line->id, 'planned_qty' => 4]);
$cancelled->update(['status' => WorkOrder::STATUS_CANCELLED]);

$units = [];
foreach (range(1, 3) as $i) {
    $units[] = SerialUnit::create(['serial_no' => sprintf('%s-SN%03d', $tag, $i), 'psn' => sprintf('%s-P%03d', $tag, $i), 'work_order_id' => $main->id])->id;
}
$reason = ScrapReason::firstOrCreate(['code' => 'E2E-NC'], ['name' => 'Cosmetic defect (e2e)', 'category' => 'method', 'is_active' => true]);

echo json_encode([
    'run' => $run,
    'tag' => $tag,
    'password' => $password,
    'line' => $line->id,
    'otherLine' => $otherLine->id,
    'main' => $main->id,
    'mainNo' => $main->order_no,
    'cancelled' => $cancelled->id,
    'units' => $units,
    'psns' => SerialUnit::whereIn('id', $units)->orderBy('id')->pluck('psn'),
    'reason' => $reason->id,
    'settings' => [
        'flow' => $setting('production_flow_mode', 'whole_batch'),
        'overproduction' => (bool) $setting('allow_overproduction', false),
        'language' => $setting('language', 'en'),
    ],
]), "\n";
