<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Workstation view's direct output (Add quantity / Log output per shift)
 * stops where the step ledger stops: no new pieces on a blocked, paused, held
 * or closed order. Correcting a shift entry already recorded stays open.
 */
class WorkstationOutputStopTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        ProductionFlow::set(ProductionFlow::WHOLE_BATCH);
        $this->line = Line::factory()->create();
        $this->operator = User::factory()->create()->assignRole('Operator');
        $this->shift = Shift::create(['line_id' => $this->line->id, 'name' => 'Morning', 'start_time' => '00:00', 'end_time' => '23:59', 'is_active' => true]);
    }

    private function order(string $status = WorkOrder::STATUS_IN_PROGRESS): WorkOrder
    {
        return WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'planned_qty' => 10,
            'produced_qty' => 0,
            'status' => $status,
            'planned_start_at' => null,
            'counting_source' => 'operator',
        ]);
    }

    private function asOperator(): static
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    private function logOutput(WorkOrder $order)
    {
        return $this->asOperator()->post("/operator/workstation/{$order->id}/shift-entry", ['shift_id' => $this->shift->id, 'quantity' => 2]);
    }

    private function addQuantity(WorkOrder $order)
    {
        return $this->asOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 2]);
    }

    public function test_a_running_order_takes_output_both_ways(): void
    {
        $order = $this->order();

        $this->logOutput($order)->assertSessionHas('success');
        $this->addQuantity($order)->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertEquals(4, (float) $order->fresh()->produced_qty);
    }

    public function test_an_order_with_an_open_blocking_issue_takes_no_output(): void
    {
        $order = $this->order(WorkOrder::STATUS_BLOCKED);
        Issue::factory()->create([
            'work_order_id' => $order->id,
            'issue_type_id' => IssueType::factory()->create(['is_blocking' => true])->id,
            'blocks_work_order' => true,
            'status' => Issue::STATUS_OPEN,
        ]);

        $this->logOutput($order)->assertSessionHas('error', 'Resolve the blocking issues before recording production.');
        $this->addQuantity($order)->assertSessionHas('error', 'Resolve the blocking issues before recording production.');

        $this->assertDatabaseCount('work_order_shift_entries', 0);
        $this->assertEquals(0, (float) $order->fresh()->produced_qty);
    }

    public function test_paused_held_rejected_and_cancelled_orders_take_no_output(): void
    {
        foreach ([WorkOrder::STATUS_PAUSED, WorkOrder::STATUS_CHANGE_HOLD, WorkOrder::STATUS_REJECTED, WorkOrder::STATUS_CANCELLED] as $status) {
            $order = $this->order($status);

            $this->logOutput($order)->assertSessionHas('error', 'Production is stopped for this work order.');
            $this->addQuantity($order)->assertSessionHas('error', 'Production is stopped for this work order.');
            $this->assertEquals(0, (float) $order->fresh()->produced_qty, $status);
        }

        $this->assertDatabaseCount('work_order_shift_entries', 0);
    }

    public function test_an_earlier_entry_can_still_be_corrected_once_the_order_is_blocked(): void
    {
        $order = $this->order();
        $this->logOutput($order)->assertSessionHas('success');
        $entry = WorkOrderShiftEntry::where('work_order_id', $order->id)->firstOrFail();

        $order->update(['status' => WorkOrder::STATUS_BLOCKED]);
        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(['key' => 'production_qty_edit_policy'], ['value' => json_encode('full')]);

        $this->asOperator()->put("/operator/shift-entry/{$entry->id}/correct", ['quantity' => 1])
            ->assertSessionHas('success');
        $this->assertEquals(1, (float) $entry->fresh()->quantity);
        $this->assertEquals(1, (float) $order->fresh()->produced_qty);
    }

    public function test_guest_cannot_log_output(): void
    {
        $order = $this->order();

        $this->post("/operator/workstation/{$order->id}/shift-entry", ['shift_id' => $this->shift->id, 'quantity' => 2])
            ->assertRedirect('/login');
        $this->assertDatabaseCount('work_order_shift_entries', 0);
    }
}
