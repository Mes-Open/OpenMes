<?php

/**
 * The shop floor the recordings start from — only what the filmed sections
 * do not create themselves: a line with three benches, a product counted in
 * pieces that has no routing yet, and three demo accounts. The routing, the
 * work order, the batch, the units and every quantity are made on camera.
 * Names carry the run's short tag so runs never collide; nothing that already
 * exists is changed.
 *
 * Run inside the app container:  RUN=K4Q2 php < seed.php      (prints ids as JSON)
 * Clean up afterwards with:      RUN=K4Q2 CLEANUP=1 php < seed.php
 */

use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$base = getenv('OPENMES_APP_PATH') ?: '/var/www/html';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$run = getenv('RUN') ?: strtoupper(substr(base_convert((string) time(), 10, 36), -4));
$tag = "REC-{$run}";

if (getenv('CLEANUP')) {
    // Soft deletes (Admin → Trash can bring them back); the accounts stay for the next run.
    $product = ProductType::where('code', "{$tag}-LAMP")->first();
    if ($product) {
        WorkOrder::where('product_type_id', $product->id)->get()->each->delete();
        $product->processTemplates()->get()->each->delete();
        $product->delete();
    }
    Line::where('code', $tag)->get()->each->delete();
    echo json_encode(['cleaned' => $tag]), "\n";

    return;
}

$setting = fn (string $key, mixed $default) => json_decode(DB::table('system_settings')->where('key', $key)->value('value') ?? json_encode($default), true);

// Accounts shared by every run, created once. An existing account's password is never reset.
$password = getenv('REC_PASSWORD') ?: 'Rec-Demo-2026!';
$account = function (string $username, string $name, string $role) use ($password) {
    $user = User::firstOrCreate(['username' => $username], ['name' => $name, 'email' => "{$username}@example.test", 'password' => Hash::make($password)]);
    $user->syncRoles([$role]);

    return $user;
};
$admin = $account('rec-admin', 'Alex Admin', 'Admin');
$supervisor = $account('rec-supervisor', 'Sam Supervisor', 'Supervisor');
$operator = $account('rec-operator', 'Olivia Operator', 'Operator');

$line = Line::create(['code' => $tag, 'name' => "Lamp line {$run}", 'is_active' => true, 'default_operator_view' => 'queue']);
$operator->lines()->syncWithoutDetaching([$line->id]);
$supervisor->lines()->syncWithoutDetaching([$line->id]);
$benches = [];
foreach (['ASM' => 'Assembly bench', 'TST' => 'Test bench', 'PAK' => 'Packing bench'] as $code => $name) {
    $benches[$code] = Workstation::create(['code' => "{$tag}-{$code}", 'line_id' => $line->id, 'name' => $name, 'is_active' => true])->id;
}

$product = ProductType::create(['code' => "{$tag}-LAMP", 'name' => "Desk lamp {$run}", 'unit_of_measure' => 'pcs', 'is_active' => true]);
$product->lines()->syncWithoutDetaching([$line->id]);

echo json_encode([
    'run' => $run,
    'tag' => $tag,
    'password' => $password,
    'line' => $line->id,
    'lineName' => $line->name,
    'benches' => $benches,
    'product' => $product->id,
    'productName' => $product->name,
    'settings' => [
        'flow' => $setting('production_flow_mode', 'whole_batch'),
        'overproduction' => (bool) $setting('allow_overproduction', false),
        'language' => $setting('language', 'en'),
    ],
]), "\n";
