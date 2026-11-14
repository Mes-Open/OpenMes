<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use App\Support\ProductionRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → System → "Allow overproduction" off: nothing an operator records
 * may take a work order over its planned quantity. On: no ceiling.
 */
class OverproductionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        Role::findOrCreate('Admin', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->line = Line::factory()->create();
    }

    private function allowOverproduction(bool $allow): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => ProductionRules::ALLOW_OVERPRODUCTION],
            ['value' => json_encode($allow)],
        );
    }

    private function actingAsOperator(): static
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    /** A work order without process steps, so the workstation records quantities directly. */
    private function plainOrder(float $planned = 10): WorkOrder
    {
        return WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'planned_qty' => $planned,
            'process_snapshot' => ['steps' => []],
        ]);
    }

    private function shift(): Shift
    {
        return Shift::create(['line_id' => $this->line->id, 'name' => 'Day', 'code' => 'D', 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true]);
    }

    // ── Batches ──────────────────────────────────────────────────────────────

    public function test_operator_cannot_create_a_batch_above_the_plan_when_overproduction_is_off(): void
    {
        $this->allowOverproduction(false);
        $order = $this->plainOrder(10);

        $this->actingAsOperator()->post('/operator/batch', ['work_order_id' => $order->id, 'target_qty' => 11])
            ->assertSessionHasErrors('target_qty');

        $this->assertSame(0, $order->batches()->count());
    }

    public function test_operator_can_create_a_batch_above_the_plan_when_overproduction_is_on(): void
    {
        $this->allowOverproduction(true);
        $order = $this->plainOrder(10);

        $this->actingAsOperator()->post('/operator/batch', ['work_order_id' => $order->id, 'target_qty' => 15])
            ->assertSessionHasNoErrors();

        $this->assertEquals(15, $order->batches()->first()->target_qty);
    }

    public function test_open_batches_count_towards_the_plan(): void
    {
        $this->allowOverproduction(false);
        $order = $this->plainOrder(10);
        Batch::factory()->create(['work_order_id' => $order->id, 'batch_number' => 1, 'target_qty' => 8, 'status' => Batch::STATUS_PENDING]);

        $this->actingAsOperator()->post('/operator/batch', ['work_order_id' => $order->id, 'target_qty' => 3])
            ->assertSessionHasErrors('target_qty');
        $this->actingAsOperator()->post('/operator/batch', ['work_order_id' => $order->id, 'target_qty' => 2])
            ->assertSessionHasNoErrors();
    }

    public function test_a_short_finished_batch_and_a_cancelled_one_leave_room_for_a_top_up(): void
    {
        $this->allowOverproduction(false);
        $order = $this->plainOrder(10);
        // Finished with 7 good of 10 (scrap), and a cancelled batch of 10.
        Batch::factory()->create(['work_order_id' => $order->id, 'batch_number' => 1, 'target_qty' => 10, 'produced_qty' => 7, 'status' => Batch::STATUS_DONE]);
        Batch::factory()->create(['work_order_id' => $order->id, 'batch_number' => 2, 'target_qty' => 10, 'status' => Batch::STATUS_CANCELLED]);

        $this->assertEquals(3, ProductionRules::batchableQty($order));
        $this->actingAsOperator()->post('/operator/batch', ['work_order_id' => $order->id, 'target_qty' => 3])
            ->assertSessionHasNoErrors();
    }

    public function test_the_work_order_page_tells_the_batch_form_its_ceiling(): void
    {
        $order = $this->plainOrder(10);
        Batch::factory()->create(['work_order_id' => $order->id, 'batch_number' => 1, 'target_qty' => 4, 'status' => Batch::STATUS_PENDING]);

        $this->allowOverproduction(false);
        $this->assertEquals(6, ProductionRules::batchableQty($order));

        $this->allowOverproduction(true);
        $this->assertNull(ProductionRules::batchableQty($order));
    }

    public function test_api_cannot_raise_a_batch_above_the_plan_when_overproduction_is_off(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->allowOverproduction(false);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $order = $this->plainOrder(10);
        $batch = Batch::factory()->create(['work_order_id' => $order->id, 'batch_number' => 1, 'target_qty' => 5, 'status' => Batch::STATUS_PENDING]);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/batches/{$batch->id}", ['target_qty' => 12])
            ->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/batches/{$batch->id}", ['target_qty' => 10])
            ->assertSuccessful();
    }

    // ── Workstation (no process steps) ───────────────────────────────────────

    public function test_workstation_cannot_record_more_than_the_plan_when_overproduction_is_off(): void
    {
        $this->allowOverproduction(false);
        $order = $this->plainOrder(10);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 11])
            ->assertSessionHas('error');
        $this->assertEquals(0, $order->fresh()->produced_qty);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHasNoErrors();
        $this->assertEquals(10, $order->fresh()->produced_qty);
    }

    public function test_workstation_can_record_more_than_the_plan_when_overproduction_is_on(): void
    {
        $this->allowOverproduction(true);
        $order = $this->plainOrder(10);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 12])
            ->assertSessionMissing('error');
        $this->assertEquals(12, $order->fresh()->produced_qty);
    }

    public function test_shift_entry_cannot_go_over_the_plan_when_overproduction_is_off(): void
    {
        $this->allowOverproduction(false);
        $order = $this->plainOrder(10);
        $shift = $this->shift();

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/shift-entry", ['shift_id' => $shift->id, 'quantity' => 8])
            ->assertSessionMissing('error');
        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/shift-entry", ['shift_id' => $shift->id, 'quantity' => 3])
            ->assertSessionHas('error');

        $this->assertEquals(8, $order->fresh()->produced_qty);
    }

    public function test_moving_to_a_done_board_status_cannot_record_more_than_the_plan(): void
    {
        $this->allowOverproduction(false);
        DB::table('system_settings')->updateOrInsert(['key' => 'workflow_mode'], ['value' => json_encode('board_status')]);
        $done = \App\Models\LineStatus::create(['name' => 'Done', 'color' => '#22c55e', 'sort_order' => 9, 'line_id' => $this->line->id, 'is_done_status' => true]);
        $order = $this->plainOrder(10);
        $move = fn (float $qty) => $this->actingAsOperator()->post("/operator/work-order/{$order->id}/line-status", ['line_status_id' => $done->id, 'produced_qty' => $qty]);

        $move(12)->assertSessionHas('error');
        $this->assertNotSame(WorkOrder::STATUS_DONE, $order->fresh()->status);

        $move(10)->assertSessionMissing('error');
        $this->assertSame(WorkOrder::STATUS_DONE, $order->fresh()->status);
        $this->assertEquals(10, $order->fresh()->produced_qty);
    }

    public function test_a_correction_cannot_raise_an_entry_over_the_plan_but_can_lower_it(): void
    {
        $this->allowOverproduction(false);
        DB::table('system_settings')->updateOrInsert(['key' => 'production_qty_edit_policy'], ['value' => json_encode('full')]);
        $order = $this->plainOrder(10);
        $entry = WorkOrderShiftEntry::create(['work_order_id' => $order->id, 'shift_id' => $this->shift()->id, 'quantity' => 8, 'user_id' => $this->operator->id, 'production_date' => today()]);
        $order->update(['produced_qty' => 8]);

        $this->actingAsOperator()->put("/operator/shift-entry/{$entry->id}/correct", ['quantity' => 11])
            ->assertSessionHasErrors('quantity');
        $this->assertEquals(8, $entry->fresh()->quantity);

        $this->actingAsOperator()->put("/operator/shift-entry/{$entry->id}/correct", ['quantity' => 6])
            ->assertSessionHasNoErrors();
        $this->assertEquals(6, $entry->fresh()->quantity);
    }
}
