<?php

namespace Tests\Integration;

use App\Models\BomItem;
use App\Models\ComponentStockReservation;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Run explicitly against a disposable PostgreSQL database with "_test" in its name. */
class ComponentStockConcurrencyTest extends TestCase
{
    public function test_concurrent_orders_cannot_reserve_the_same_stock(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires real PostgreSQL row locks.');
        }
        $this->assertStringContainsString('_test', DB::connection()->getDatabaseName(), 'Use a disposable test database.');
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $parent = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => ProductType::factory()->create(['unit_of_measure' => 'pcs'])->id]);
        $part = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => ProductType::factory()->create(['unit_of_measure' => 'pcs'])->id]);
        BomItem::create(['process_template_id' => $parent->id, 'product_type_id' => $part->product_type_id, 'quantity_per_unit' => 6]);
        $warehouse = Warehouse::factory()->create();
        $stock = WarehouseStock::create(['warehouse_id' => $warehouse->id, 'product_type_id' => $part->product_type_id, 'quantity' => 50, 'unit_of_measure' => 'pcs']);
        $orders = collect([1, 2])->map(fn ($n) => app(WorkOrderService::class)->createWorkOrder(['order_no' => 'RACE-'.$n, 'product_type_id' => $parent->product_type_id, 'planned_qty' => 20]));
        $processes = $orders->map(fn ($order) => new Process([PHP_BINARY, base_path('tests/Support/component-stock-worker.php'), (string) $order->id, (string) $warehouse->id], base_path(), null, null, 30));
        // Keep both independent connections waiting on the same balance before releasing them.
        DB::beginTransaction();
        WarehouseStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
        try {
            foreach ($processes as $process) {
                $process->start();
            }
            $deadline = microtime(true) + 15;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail($process->getErrorOutput().$process->getOutput());
                    }
                }
                $waiting = DB::selectOne("SELECT COUNT(*) AS n FROM pg_stat_activity WHERE datname = current_database() AND wait_event_type = 'Lock' AND query LIKE '%warehouse_stocks%'")->n;
                if ((int) $waiting === 2) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            $this->assertEquals(2, $waiting, 'Both workers must overlap while waiting for the balance.');
        } finally {
            DB::commit();
        }
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        }
        $this->assertEquals(50, ComponentStockReservation::where('status', 'held')->sum('quantity'));
        $this->assertEqualsCanonicalizing([70, 120], WorkOrder::whereIn('parent_work_order_id', $orders->pluck('id'))->pluck('planned_qty')->all());
        $this->assertEquals(50, $stock->fresh()->quantity);
    }
}
