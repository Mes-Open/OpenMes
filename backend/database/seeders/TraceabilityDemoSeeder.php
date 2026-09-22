<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\Pallet;
use App\Models\ProductType;
use App\Models\QualityCheck;
use App\Models\QualityCheckSample;
use App\Models\SerialUnit;
use App\Models\UnitStepHistory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Opt-in, repeatable demo for the traceability console. Never run as part of
 * the default database seed:
 *
 *   php artisan db:seed --class=TraceabilityDemoSeeder
 *
 * One connected story, every identifier prefixed TR-: supplier steel and paint
 * lots -> a semi-finished frame lot -> finished bikes on pallets, with serial
 * numbers and a customer order on top. It is written straight to the genealogy
 * tables rather than driven through allocation, so it traces the same whether
 * or not lot tracking is switched on. Additive only - it touches no existing row.
 */
class TraceabilityDemoSeeder extends Seeder
{
    private const MARKER_ORDER = 'TR-FRAME-001';

    private const CUSTOMER_ORDER = 'TR-CO-5001';

    private User $operator;

    public function run(): void
    {
        if (WorkOrder::where('order_no', self::MARKER_ORDER)->exists()) {
            $this->command?->info('Traceability demo already present - nothing to do.');

            return;
        }

        $operator = User::orderBy('id')->first();
        if (! $operator) {
            $this->command?->warn('Traceability demo needs at least one user.');

            return;
        }
        $this->operator = $operator;

        DB::transaction(function () {
            [$line, $stations] = $this->seedLine();
            $materials = $this->seedMaterials();
            $lots = $this->seedRawLots($materials);

            $frameLot = $this->seedFrameOrder($line, $stations, $materials, $lots);
            $this->seedFinishedBikeOrder($line, $stations, $materials, $lots, $frameLot);
            $this->seedRunningBikeOrder($line, $stations, $lots, $frameLot);
            $this->seedPendingBikeOrder($line);
        });

        $this->command?->info('Traceability demo seeded - open /admin/traceability and look for TR-.');
    }

    /** @return array{0: Line, 1: array<string, Workstation>} */
    private function seedLine(): array
    {
        $line = Line::firstOrCreate(['code' => 'TR-LINE'], ['name' => 'TR - Bike assembly', 'is_active' => true]);

        $stations = [];
        foreach (['CUT' => ['Tube cutting', 40], 'WELD' => ['Frame welding', 24], 'PAINT' => ['Paint booth', 30], 'ASSY' => ['Final assembly', 12]] as $code => [$name, $rate]) {
            $stations[$code] = Workstation::firstOrCreate(
                ['code' => "TR-{$code}"],
                ['line_id' => $line->id, 'name' => $name, 'ideal_rate_per_hour' => $rate, 'is_active' => true],
            );
        }

        return [$line, $stations];
    }

    /** @return array<string, Material> */
    private function seedMaterials(): array
    {
        $typeId = MaterialType::orderBy('id')->value('id')
            ?? MaterialType::create(['code' => 'TR-RAW', 'name' => 'TR - Raw material'])->id;

        $defs = [
            'steel' => ['TR-MAT-STEEL', 'Steel tube 32x2', 'm'],
            'paint' => ['TR-MAT-PAINT', 'Powder paint RAL 3020', 'kg'],
            'frame' => ['TR-MAT-FRAME', 'Welded bike frame', 'pcs'],
            'bike' => ['TR-MAT-BIKE', 'City bike (finished)', 'pcs'],
        ];

        $materials = [];
        foreach ($defs as $key => [$code, $name, $unit]) {
            $materials[$key] = Material::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'material_type_id' => $typeId, 'unit_of_measure' => $unit, 'tracking_type' => 'batch', 'is_active' => true],
            );
        }

        return $materials;
    }

    /** @return array<string, MaterialLot> */
    private function seedRawLots(array $materials): array
    {
        $defs = [
            'steel1' => ['TR-RAW-STEEL-01', 'steel', 600, 120, 'SUP-ST-7781', 'TR-CONT-0042', MaterialLot::STATUS_RELEASED, 21],
            'steel2' => ['TR-RAW-STEEL-02', 'steel', 600, 510, 'SUP-ST-7802', 'TR-CONT-0057', MaterialLot::STATUS_RELEASED, 6],
            'paint1' => ['TR-RAW-PAINT-01', 'paint', 50, 31.5, 'SUP-PT-3020-114', 'TR-DRUM-0009', MaterialLot::STATUS_RELEASED, 14],
            // Held at inbound inspection and never consumed: a lot with no forward trace.
            'steel3' => ['TR-RAW-STEEL-03', 'steel', 600, 600, 'SUP-ST-7815', 'TR-CONT-0063', MaterialLot::STATUS_QUARANTINE, 2],
        ];

        $lots = [];
        foreach ($defs as $key => [$lotNo, $material, $received, $available, $supplierLot, $container, $status, $daysAgo]) {
            $lots[$key] = MaterialLot::create([
                'lot_number' => $lotNo,
                'material_id' => $materials[$material]->id,
                'quantity_received' => $received,
                'quantity_available' => $available,
                'unit_of_measure' => $materials[$material]->unit_of_measure,
                'received_at' => now()->subDays($daysAgo),
                'status' => $status,
                'supplier_lot_no' => $supplierLot,
                'supplier_reference' => 'PO-'.(4400 + $daysAgo),
                'source_container_no' => $container,
                'hold_reason' => $status === MaterialLot::STATUS_QUARANTINE ? 'Wall thickness out of tolerance on inbound sample' : null,
                'held_at' => $status === MaterialLot::STATUS_QUARANTINE ? now()->subDays($daysAgo) : null,
                'created_by_id' => $this->operator->id,
            ]);
        }

        return $lots;
    }

    /** Semi-finished: steel -> welded frames, output as a lot the bike orders consume. */
    private function seedFrameOrder(Line $line, array $stations, array $materials, array $lots): MaterialLot
    {
        $start = now()->subDays(12)->setTime(6, 10);
        $order = $this->order('TR-FRAME-001', $line, 'TR-PT-FRAME', 'Welded bike frame', 60, 60, WorkOrder::STATUS_DONE, null, $start->copy()->addHours(7));
        $batch = $this->batch($order, 1, 'TR-FRM-L001', 60, 60, Batch::STATUS_DONE, $start, $start->copy()->addHours(7));

        $cut = $this->step($batch, 1, 'Cut tubes', $stations['CUT'], $start, 95, 62, 2, 10, 1.5);
        $weld = $this->step($batch, 2, 'Weld frame', $stations['WELD'], $start->copy()->addMinutes(100), 310, 60, 2, 15, 5);

        $this->consume($cut, $lots['steel1'], 186, $cut->started_at);
        $this->consume($weld, $lots['steel1'], 12, $weld->started_at);

        return MaterialLot::create([
            'lot_number' => 'TR-SEMI-FRAME-01',
            'material_id' => $materials['frame']->id,
            'quantity_received' => 60,
            'quantity_available' => 8,
            'unit_of_measure' => 'pcs',
            'received_at' => $batch->completed_at,
            'manufacturing_date' => Carbon::parse($batch->completed_at)->toDateString(),
            'status' => MaterialLot::STATUS_RELEASED,
            'source_batch_id' => $batch->id,
            'created_by_id' => $this->operator->id,
        ]);
    }

    /** Finished and shipped: frames + paint -> bikes, pallets, serials, QC, customer order. */
    private function seedFinishedBikeOrder(Line $line, array $stations, array $materials, array $lots, MaterialLot $frameLot): void
    {
        $start = now()->subDays(8)->setTime(6, 5);
        $end = $start->copy()->addHours(6);
        $order = $this->order('TR-BIKE-001', $line, 'TR-PT-BIKE', 'City bike', 30, 30, WorkOrder::STATUS_DONE, self::CUSTOMER_ORDER, $end);
        $batch = $this->batch($order, 1, 'TR-FG-L001', 30, 30, Batch::STATUS_DONE, $start, $end);

        $paint = $this->step($batch, 1, 'Powder coat', $stations['PAINT'], $start, 80, 31, 1, 10, 2);
        $assy = $this->step($batch, 2, 'Final assembly', $stations['ASSY'], $start->copy()->addMinutes(90), 260, 30, 1, 10, 5);

        $this->consume($paint, $frameLot, 32, $paint->started_at);
        $this->consume($paint, $lots['paint1'], 9.6, $paint->started_at);

        MaterialLot::create([
            'lot_number' => 'TR-FG-BIKE-01',
            'material_id' => $materials['bike']->id,
            'quantity_received' => 30,
            'quantity_available' => 0,
            'unit_of_measure' => 'pcs',
            'received_at' => $end,
            'status' => MaterialLot::STATUS_RELEASED,
            'source_batch_id' => $batch->id,
            'created_by_id' => $this->operator->id,
        ]);

        $shipped = Pallet::create([
            'work_order_id' => $order->id, 'batch_id' => $batch->id, 'qty' => 20, 'status' => 'shipped',
            'quality_status' => 'pass', 'location' => 'DOCK-2', 'destination' => 'Velo Nord GmbH, Hamburg', 'erp_reference' => 'TR-WZ-0871',
        ]);
        $shipped->forceFill(['shipped_at' => $end->copy()->addDays(2)])->saveQuietly();
        $closed = Pallet::create([
            'work_order_id' => $order->id, 'batch_id' => $batch->id, 'qty' => 10, 'status' => 'closed',
            'quality_status' => 'pass', 'location' => 'FG-A-03-01',
        ]);

        $check = QualityCheck::create([
            'batch_id' => $batch->id, 'pallet_id' => $closed->id, 'checked_by' => $this->operator->id,
            'checked_at' => $end->copy()->subMinutes(20), 'production_quantity' => 30, 'all_passed' => true,
            'notes' => 'Final inspection before packing.',
        ]);
        foreach ([['Coating thickness [um]', 'numeric', 82.4, null], ['Brake test', 'boolean', null, true], ['Wheel runout [mm]', 'numeric', 0.6, null]] as $i => [$name, $type, $numeric, $bool]) {
            QualityCheckSample::create([
                'quality_check_id' => $check->id, 'sample_number' => $i + 1, 'parameter_name' => $name,
                'parameter_type' => $type, 'value_numeric' => $numeric, 'value_boolean' => $bool, 'is_passed' => true,
            ]);
        }

        // Serial units: most pass both stations, one needed rework, one was scrapped.
        foreach (range(1, 6) as $n) {
            $failed = $n === 5;
            $unit = SerialUnit::create([
                'serial_no' => sprintf('TR-SN-%04d', $n),
                'work_order_id' => $order->id,
                'batch_id' => $batch->id,
                'material_id' => $materials['bike']->id,
                'status' => $failed ? 'scrapped' : ($n <= 4 ? 'shipped' : 'completed'),
                'produced_at' => $failed ? null : $end,
            ]);
            $at = $paint->started_at->copy()->addMinutes(10 + $n * 3);
            $this->history($unit, $paint, $stations['PAINT'], $at, 'pass', ['oven_temp_c' => 190 + $n, 'cure_min' => 12]);
            if ($n === 3) {
                $this->history($unit, $assy, $stations['ASSY'], $at->copy()->addMinutes(95), 'rework', ['torque_nm' => 31.2], 'Crank bolt under torque - retightened.');
            }
            $this->history($unit, $assy, $stations['ASSY'], $at->copy()->addMinutes(110), $failed ? 'fail' : 'pass', ['torque_nm' => $failed ? 22.5 : 40.1], $failed ? 'Cracked weld found at head tube.' : null);
        }
    }

    /**
     * Still running, the way Transfer flow looks mid-order: both stations open
     * at once, pieces waiting between them, some scrap already logged.
     */
    private function seedRunningBikeOrder(Line $line, array $stations, array $lots, MaterialLot $frameLot): void
    {
        $start = now()->subHours(5);
        $order = $this->order('TR-BIKE-002', $line, 'TR-PT-BIKE', 'City bike', 20, 9, WorkOrder::STATUS_IN_PROGRESS, self::CUSTOMER_ORDER, null);
        $batch = $this->batch($order, 1, 'TR-FG-L002', 20, 9, Batch::STATUS_IN_PROGRESS, $start, null);

        $paint = $this->step($batch, 1, 'Powder coat', $stations['PAINT'], $start, null, 16, 1, 10, 2);
        $assy = $this->step($batch, 2, 'Final assembly', $stations['ASSY'], $start->copy()->addMinutes(45), null, 9, 0, 10, 5);

        $this->consume($paint, $frameLot, 20, $paint->started_at);
        $this->consume($paint, $lots['paint1'], 5.4, $paint->started_at);
        // A second steel lot enters here (replacement brackets), so this order's
        // genealogy differs from TR-BIKE-001 although the product is the same.
        $this->consume($assy, $lots['steel2'], 4, $assy->started_at);

        Pallet::create([
            'work_order_id' => $order->id, 'batch_id' => $batch->id, 'qty' => 9, 'status' => 'open',
            'quality_status' => 'pending', 'location' => 'LINE-END',
        ]);
    }

    private function seedPendingBikeOrder(Line $line): void
    {
        $this->order('TR-BIKE-003', $line, 'TR-PT-BIKE', 'City bike', 40, 0, WorkOrder::STATUS_PENDING, 'TR-CO-5002', null);
    }

    // ── builders ─────────────────────────────────────────────────────────

    private function order(string $no, Line $line, string $productCode, string $productName, int $planned, int $produced, string $status, ?string $customerOrder, ?Carbon $completedAt): WorkOrder
    {
        $product = ProductType::firstOrCreate(['code' => $productCode], ['name' => "TR - {$productName}", 'is_active' => true]);

        return WorkOrder::create([
            'order_no' => $no,
            'line_id' => $line->id,
            'product_type_id' => $product->id,
            'customer_order_no' => $customerOrder,
            'planned_qty' => $planned,
            'produced_qty' => $produced,
            'status' => $status,
            'priority' => 3,
            'due_date' => now()->addDays(10)->toDateString(),
            'completed_at' => $completedAt,
            'description' => 'Traceability demo',
        ]);
    }

    private function batch(WorkOrder $order, int $number, string $lot, int $target, int $produced, string $status, Carbon $startedAt, ?Carbon $completedAt): Batch
    {
        return Batch::create([
            'work_order_id' => $order->id,
            'batch_number' => $number,
            'lot_number' => $lot,
            'target_qty' => $target,
            'produced_qty' => $produced,
            'status' => $status,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);
    }

    /** $minutes null = still running. Times and quantities are what a station timeline will plot. */
    private function step(Batch $batch, int $number, string $name, Workstation $station, Carbon $startedAt, ?int $minutes, float $passed, float $scrap, int $setupMinutes, float $runPerUnit): BatchStep
    {
        $done = $minutes !== null;

        return BatchStep::create([
            'batch_id' => $batch->id,
            'step_number' => $number,
            'name' => $name,
            'status' => $done ? BatchStep::STATUS_DONE : BatchStep::STATUS_IN_PROGRESS,
            'workstation_id' => $station->id,
            'started_at' => $startedAt,
            'started_by_id' => $this->operator->id,
            'completed_at' => $done ? $startedAt->copy()->addMinutes($minutes) : null,
            'completed_by_id' => $done ? $this->operator->id : null,
            'duration_minutes' => $minutes,
            'setup_time_minutes' => $setupMinutes,
            'run_time_per_unit_minutes' => $runPerUnit,
            'passed_qty' => $passed,
            'scrap_qty' => $scrap,
        ]);
    }

    private function consume(BatchStep $step, MaterialLot $lot, float $qty, $at): void
    {
        BatchStepLotConsumption::create([
            'batch_step_id' => $step->id,
            'material_lot_id' => $lot->id,
            'quantity_consumed' => $qty,
            'consumed_at' => $at,
            'recorded_by_id' => $this->operator->id,
        ]);
    }

    private function history(SerialUnit $unit, BatchStep $step, Workstation $station, Carbon $at, string $result, array $parameters, ?string $notes = null): void
    {
        UnitStepHistory::create([
            'serial_unit_id' => $unit->id,
            'batch_step_id' => $step->id,
            'workstation_id' => $station->id,
            'operator_id' => $this->operator->id,
            'parameters' => $parameters,
            'result' => $result,
            'notes' => $notes,
            'processed_at' => $at,
        ]);
    }
}
