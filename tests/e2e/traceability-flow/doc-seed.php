<?php

/**
 * Generic demo data for the traceability walkthrough's screenshots, loaded
 * into a clean instance (see doc-screenshots.spec.ts). Nothing here belongs to
 * a real product: one line with three benches, one product with a three-step
 * routing (assembly, test, packing), its number sequences, one component with
 * a lot, packaging materials and two demo accounts - plus, for the second
 * example, a sub-assembly bench and product, a finished-goods warehouse and an
 * order that stock cannot cover.
 *
 * Run inside the app container:  php < doc-seed.php   (prints the ids as JSON)
 */

use App\Models\BomItem;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$base = getenv('OPENMES_APP_PATH') ?: '/work';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$setting = fn (string $key, mixed $value) => DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
foreach ([
    'language' => 'pl',
    'onboarding_completed' => true,
    'production_flow_mode' => 'transfer',
    'production_tracking_mode' => 'per_operation',
    'workstation_routing_enabled' => true,
    'force_sequential_steps' => true,
    'lot_tracking_enabled' => false,
    'unit_identifier_normalize' => true,
    'unit_psn_unique' => true,
    'unit_test_fail_policy' => 'block',
    'unit_test_max_attempts' => 3,
    'unit_test_attempts_scope' => 'test',
    'pallet_stock_documents' => 'post',
    'hold_on_material_shortage' => true,
    'enabled_modules' => ['reports', 'materials', 'product_engineering', 'quality', 'structure', 'packaging', 'warehouse'],
] as $key => $value) {
    $setting($key, $value);
}

$password = getenv('DOC_PASSWORD') ?: 'demo-pass-2026';
$admin = User::firstOrCreate(['username' => 'demo-admin'], ['name' => 'Administrator', 'email' => 'demo-admin@example.test', 'password' => Hash::make($password)]);
$admin->syncRoles(['Admin']);
$operator = User::firstOrCreate(['username' => 'demo-operator'], ['name' => 'Operator', 'email' => 'demo-operator@example.test', 'password' => Hash::make($password)]);
$operator->syncRoles(['Operator']);

$line = Line::firstOrCreate(['code' => 'L1'], ['name' => 'Linia 1', 'is_active' => true, 'default_operator_view' => 'queue']);
$operator->lines()->syncWithoutDetaching([$line->id]);
$assembly = Workstation::firstOrCreate(['code' => 'ST-MONT'], ['line_id' => $line->id, 'name' => 'Montaż', 'is_active' => true]);
$test = Workstation::firstOrCreate(['code' => 'ST-TEST'], ['line_id' => $line->id, 'name' => 'Test', 'is_active' => true]);
$packing = Workstation::firstOrCreate(['code' => 'ST-PAK'], ['line_id' => $line->id, 'name' => 'Pakowanie', 'is_active' => true]);

$product = ProductType::firstOrCreate(['code' => 'PRD-A'], ['name' => 'Produkt A', 'is_active' => true]);
$product->lines()->syncWithoutDetaching([$line->id]);
$type = MaterialType::query()->first();
$component = Material::firstOrCreate(['code' => 'CMP-A'], ['name' => 'Komponent A', 'material_type_id' => $type?->id, 'unit_of_measure' => 'pcs', 'tracking_type' => 'batch', 'stock_quantity' => 1000]);
$box = Material::firstOrCreate(['code' => 'BOX-2'], ['name' => 'Karton zbiorczy', 'material_type_id' => $type?->id, 'unit_of_measure' => 'pcs', 'tracking_type' => 'batch', 'stock_quantity' => 200]);
$pallet = Material::firstOrCreate(['code' => 'PAL-STD'], ['name' => 'Paleta', 'material_type_id' => $type?->id, 'unit_of_measure' => 'pcs', 'tracking_type' => 'batch', 'stock_quantity' => 50]);
MaterialLot::firstOrCreate(['lot_number' => 'LOT-A-001'], ['material_id' => $component->id, 'quantity_received' => 1000, 'quantity_available' => 1000, 'unit_of_measure' => 'pcs', 'received_at' => now()->subDays(3), 'status' => MaterialLot::STATUS_RELEASED]);

$template = ProcessTemplate::firstOrCreate(['product_type_id' => $product->id, 'name' => 'Marszruta A'], ['version' => 1, 'is_active' => true]);
$s1 = TemplateStep::firstOrCreate(['process_template_id' => $template->id, 'step_number' => 1], ['name' => 'Montaż', 'workstation_id' => $assembly->id, 'estimated_duration_minutes' => 10]);
$s2 = TemplateStep::firstOrCreate(['process_template_id' => $template->id, 'step_number' => 2], ['name' => 'Test', 'workstation_id' => $test->id, 'estimated_duration_minutes' => 5]);
$s3 = TemplateStep::firstOrCreate(['process_template_id' => $template->id, 'step_number' => 3], [
    'name' => 'Pakowanie', 'workstation_id' => $packing->id, 'estimated_duration_minutes' => 3, 'kind' => TemplateStep::KIND_PACKING,
    'config' => TemplateStep::normalisePackingConfig(['unit' => 'carton', 'carton_capacity' => 2, 'pallet_capacity' => 1, 'unit_label' => false, 'weight_expected_g' => 500, 'weight_tolerance_g' => 10]),
]);
BomItem::firstOrCreate(['process_template_id' => $template->id, 'material_id' => $component->id], ['template_step_id' => $s1->id, 'quantity_per_unit' => 1, 'per' => BomItem::PER_UNIT, 'consumed_at' => 'during', 'scrap_percentage' => 0]);
BomItem::firstOrCreate(['process_template_id' => $template->id, 'material_id' => $box->id], ['template_step_id' => $s3->id, 'quantity_per_unit' => 1, 'per' => BomItem::PER_CARTON, 'consumed_at' => 'during', 'scrap_percentage' => 0]);
BomItem::firstOrCreate(['process_template_id' => $template->id, 'material_id' => $pallet->id], ['template_step_id' => $s3->id, 'quantity_per_unit' => 1, 'per' => BomItem::PER_PALLET, 'consumed_at' => 'during', 'scrap_percentage' => 0]);

foreach ([
    [LotSequence::PURPOSE_PROCESS_SERIAL, 'PSN produktu A', 'PSN-[year2][doy]-[seq]'],
    [LotSequence::PURPOSE_UNIT_SERIAL, 'SN produktu A', 'S[year1][week][weekday]-[seq]'],
    [LotSequence::PURPOSE_LOT, 'Partie produktu A', 'B[year2][doy]-[seq]'],
] as [$purpose, $name, $pattern]) {
    LotSequence::firstOrCreate(['product_type_id' => $product->id, 'purpose' => $purpose], ['name' => $name, 'prefix' => '', 'pattern' => $pattern, 'pad_size' => 4, 'next_number' => 1, 'reset_period' => 'none']);
}

// Second example: a bench making a sub-assembly tracked by its own serial, a
// finished-goods warehouse the pallets are booked into, and an order too big for stock.
App\Models\Warehouse::firstOrCreate(['code' => 'FG-1'], ['name' => 'Magazyn wyrobów gotowych', 'kind' => 'finished_goods', 'is_default' => true, 'is_active' => true]);
$partsBench = Workstation::firstOrCreate(['code' => 'ST-POD'], ['line_id' => $line->id, 'name' => 'Podzespoły', 'is_active' => true]);
$module = ProductType::firstOrCreate(['code' => 'PRD-M'], ['name' => 'Moduł B', 'is_active' => true]);
$module->lines()->syncWithoutDetaching([$line->id]);
$moduleRouting = ProcessTemplate::firstOrCreate(['product_type_id' => $module->id, 'name' => 'Marszruta modułu B'], ['version' => 1, 'is_active' => true]);
TemplateStep::firstOrCreate(['process_template_id' => $moduleRouting->id, 'step_number' => 1], ['name' => 'Montaż modułu', 'workstation_id' => $partsBench->id, 'estimated_duration_minutes' => 5]);
Material::firstOrCreate(['code' => 'MOD-B'], ['name' => 'Moduł B', 'material_type_id' => $type?->id, 'unit_of_measure' => 'pcs', 'tracking_type' => 'serial',
    'is_manufactured' => true, 'producing_process_template_id' => $moduleRouting->id, 'stock_quantity' => 0]);
$orders = app(App\Services\WorkOrder\WorkOrderService::class);
$partsOrder = WorkOrder::where('order_no', 'ZL-POD-1')->first()
    ?? $orders->createWorkOrder(['order_no' => 'ZL-POD-1', 'product_type_id' => $module->id, 'line_id' => $line->id, 'planned_qty' => 3]);
$heldOrder = WorkOrder::where('order_no', 'ZL-0002')->first()
    ?? $orders->createWorkOrder(['order_no' => 'ZL-0002', 'product_type_id' => $product->id, 'line_id' => $line->id, 'planned_qty' => 2000]);
if (! $heldOrder->batches()->exists()) {
    $orders->createBatch($heldOrder, 2000);
}

// An error code for holding a non-conforming unit, beside the seeded scrap reasons.
App\Models\ScrapReason::firstOrCreate(['code' => 'WIZ-01'], ['name' => 'Uszkodzenie wizualne', 'category' => 'method', 'is_active' => true]);

$tester = User::firstOrCreate(['username' => 'demo-tester'], ['name' => 'Tester', 'email' => 'demo-tester@example.test', 'password' => Hash::make($password)]);
$tester->syncRoles(['Operator']);
$tester->tokens()->delete();

echo json_encode([
    'line' => $line->id, 'assembly' => $assembly->id, 'test' => $test->id, 'packing' => $packing->id,
    'partsBench' => $partsBench->id, 'partsOrder' => $partsOrder->id, 'heldOrder' => $heldOrder->id,
    'lotA' => MaterialLot::where('lot_number', 'LOT-A-001')->value('id'),
    'product' => $product->id, 'template' => $template->id, 'packingStep' => $s3->id,
    'cartonTemplate' => App\Models\LabelTemplate::where('type', 'carton')->value('id'),
    'psnSequence' => LotSequence::where('product_type_id', $product->id)->where('purpose', LotSequence::PURPOSE_PROCESS_SERIAL)->value('id'),
    'testerToken' => $tester->createToken('doc-tester')->plainTextToken,
]), "\n";
