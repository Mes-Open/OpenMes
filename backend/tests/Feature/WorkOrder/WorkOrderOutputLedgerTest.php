<?php

namespace Tests\Feature\WorkOrder;

use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Models\WorkOrderOutputEntry;
use App\Services\WorkOrder\MachineProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every change to a work order's produced_qty lands in the output ledger with
 * its time, line and tenant — the source of the dashboard's output per hour.
 */
class WorkOrderOutputLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_produced_qty_change_writes_one_entry(): void
    {
        $tenant = Tenant::create(['name' => 'Plant']);
        $order = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'produced_qty' => 10]);
        $this->travelTo(now()->setTime(9, 15));

        $order->update(['produced_qty' => 35]);

        $entry = WorkOrderOutputEntry::sole();
        $this->assertSame($order->id, $entry->work_order_id);
        $this->assertSame($order->line_id, $entry->line_id);
        $this->assertSame($tenant->id, $entry->tenant_id);
        $this->assertSame('25.00', $entry->delta);
        $this->assertSame('35.00', $entry->produced_total);
        $this->assertTrue($entry->recorded_at->equalTo(now()));
    }

    public function test_a_correction_down_is_a_negative_entry(): void
    {
        $order = WorkOrder::factory()->create(['produced_qty' => 50]);

        $order->update(['produced_qty' => 42]);

        $this->assertSame('-8.00', WorkOrderOutputEntry::sole()->delta);
    }

    public function test_saves_that_leave_produced_qty_alone_write_nothing(): void
    {
        $order = WorkOrder::factory()->create(['produced_qty' => 50]);

        $order->update(['description' => 'Rush order']);
        $order->update(['produced_qty' => '50.00']);

        $this->assertSame(0, WorkOrderOutputEntry::count());
    }

    public function test_creating_an_order_with_output_writes_nothing(): void
    {
        WorkOrder::factory()->create(['produced_qty' => 120]);

        $this->assertSame(0, WorkOrderOutputEntry::count());
    }

    public function test_machine_counter_path_without_a_user_is_recorded(): void
    {
        $order = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'counting_source' => WorkOrder::COUNTING_MACHINE,
            'planned_qty' => 100,
        ]);
        $this->assertFalse(auth()->check());

        $this->assertTrue(app(MachineProductionService::class)->recordGoodCount($order, 7));

        $this->assertSame('7.00', WorkOrderOutputEntry::sole()->delta);
    }

    public function test_a_rolled_back_transaction_takes_the_entry_with_it(): void
    {
        $order = WorkOrder::factory()->create(['produced_qty' => 0]);

        try {
            DB::transaction(function () use ($order) {
                $order->update(['produced_qty' => 5]);
                throw new \RuntimeException('abort');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, WorkOrderOutputEntry::count());
    }
}
