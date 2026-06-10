<?php

namespace Database\Seeders;

use App\Models\AdditionalCost;
use App\Models\Batch;
use App\Models\EmployeeActivity;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\Worker;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo cost data for the Production Cost report. Backfills finished work orders
 * with priced material consumption, productive labor (one of each pay mode) and
 * an additional cost, so the report shows realistic non-zero values.
 *
 * Run with: `php artisan db:seed --class=ProductionCostDemoSeeder`.
 * Idempotent: wipes the cost rows it owns for the target work orders, then
 * re-creates them, so re-running does not accumulate duplicates.
 */
class ProductionCostDemoSeeder extends Seeder
{
    public function run(): void
    {
        $workers = Worker::orderBy('id')->take(3)->get();
        if ($workers->count() < 1) {
            $this->command?->warn('ProductionCostDemoSeeder: no workers — run HrDemoSeeder first.');

            return;
        }

        // Give the first workers one of each compensation mode.
        $modes = [
            ['pay_type' => 'hourly', 'pay_rate' => 45],
            ['pay_type' => 'weekly', 'pay_rate' => 4000],
            ['pay_type' => 'piece_rate', 'pay_rate' => 1.50],
        ];
        foreach ($workers as $i => $worker) {
            $worker->update($modes[$i] ?? $modes[0]);
        }

        // Price two materials used in the demo consumption.
        $materials = Material::orderBy('id')->take(2)->get();
        $prices = [0.85, 18.50];
        foreach ($materials as $i => $material) {
            if ($material->unit_price === null || (float) $material->unit_price == 0.0) {
                $material->update(['unit_price' => $prices[$i] ?? 5, 'price_currency' => 'PLN']);
            }
        }

        $targets = WorkOrder::where('status', WorkOrder::STATUS_DONE)
            ->where('produced_qty', '>', 0)
            ->orderBy('id')
            ->take(3)
            ->get();

        if ($targets->isEmpty()) {
            $this->command?->warn('ProductionCostDemoSeeder: no completed work orders with output found.');

            return;
        }

        $now = CarbonImmutable::now();

        foreach ($targets as $idx => $wo) {
            // Idempotency: drop the cost rows this seeder owns for the WO.
            MaterialAllocation::where('work_order_id', $wo->id)->delete();
            EmployeeActivity::where('work_order_id', $wo->id)->delete();
            AdditionalCost::where('work_order_id', $wo->id)->delete();

            $batch = Batch::firstOrCreate(
                ['work_order_id' => $wo->id, 'batch_number' => 1],
                ['target_qty' => $wo->produced_qty, 'produced_qty' => $wo->produced_qty, 'status' => 'DONE'],
            );

            $produced = (float) $wo->produced_qty;

            // Material consumption with a price snapshot (so cost is stable).
            foreach ($materials as $m => $material) {
                $qty = round($produced * ($m === 0 ? 0.05 : 1.0), 4); // ink vs blanks
                MaterialAllocation::create([
                    'batch_id' => $batch->id,
                    'material_id' => $material->id,
                    'work_order_id' => $wo->id,
                    'allocated_qty' => $qty,
                    'consumed_qty' => $qty,
                    'status' => MaterialAllocation::STATUS_CONSUMED,
                    'allocated_at' => $now->subHours(6),
                    'consumed_at' => $now->subHours(2),
                    'unit_price_snapshot' => $material->unit_price,
                    'price_currency_snapshot' => 'PLN',
                ]);
            }

            // Labor: rotate workers so each report row shows a different mix.
            foreach ($workers as $w => $worker) {
                if (($w + $idx) % 2 === 0 || $w === 0) {
                    $start = $now->subHours(8 - $w);
                    EmployeeActivity::create([
                        'worker_id' => $worker->id,
                        'type' => 'work',
                        'work_order_id' => $wo->id,
                        'line_id' => $wo->line_id,
                        'starts_at' => $start,
                        'ends_at' => $start->addHours(4 + $w),
                        'step_name' => 'Production',
                    ]);
                }
            }

            // Additional cost (energy / overhead).
            AdditionalCost::create([
                'work_order_id' => $wo->id,
                'description' => 'Energy & machine overhead',
                'amount' => 120 + $idx * 35,
                'currency' => 'PLN',
            ]);
        }

        $this->command?->info("ProductionCostDemoSeeder: seeded cost data for {$targets->count()} work orders.");
    }
}
