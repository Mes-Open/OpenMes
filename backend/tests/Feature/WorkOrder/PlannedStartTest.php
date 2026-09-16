<?php

namespace Tests\Feature\WorkOrder;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\MachineProductionService;
use App\Support\ProductionFlow;
use App\Support\TimezoneRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlannedStartTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        TimezoneRegistry::save('Europe/Warsaw');
        TimezoneRegistry::refresh();
        $this->travelTo(Carbon::parse('2026-09-16 10:00:00', 'Europe/Warsaw'));
        $this->admin = User::factory()->create()->assignRole('Admin');
        $this->operator = User::factory()->create()->assignRole('Operator');
        $this->line = Line::factory()->create();
        ProductionFlow::set(ProductionFlow::WHOLE_BATCH);
    }

    private function order(array $attributes = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'line_id' => $this->line->id,
            'planned_qty' => 10,
            'due_date' => '2026-09-30',
            'planned_start_at' => '2026-09-16 10:30:00',
        ], $attributes));
    }

    public function test_create_and_edit_preserve_plant_local_start_separate_from_deadline(): void
    {
        $data = ['order_no' => 'START-TEST', 'line_id' => $this->line->id, 'planned_qty' => 10,
            'planned_start_at' => '2026-09-17T08:15', 'due_date' => '2026-09-30'];
        $this->actingAs($this->admin)->post('/admin/work-orders', $data)->assertSessionHasNoErrors();
        $order = WorkOrder::where('order_no', 'START-TEST')->firstOrFail();
        $this->assertSame('2026-09-17 08:15', $order->planned_start_at->format('Y-m-d H:i'));
        $this->get('/admin/work-orders/'.$order->id.'/edit')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('workOrder.planned_start_at', '2026-09-17T08:15'));
        $this->put('/admin/work-orders/'.$order->id, array_merge($data, [
            'planned_start_at' => '2026-09-18T09:45', 'status' => 'PENDING',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('2026-09-18 09:45', $order->fresh()->planned_start_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-30', $order->fresh()->due_date->toDateString());
    }

    public function test_operator_lists_release_at_start_and_keep_legacy_orders(): void
    {
        $future = $this->order();
        $legacy = $this->order(['planned_start_at' => null]);
        $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
        $this->get('/operator/queue?workstation=all')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('activeWorkOrders', 1)->where('activeWorkOrders.0.id', $legacy->id));
        $this->get('/operator/workstation?workstation=all')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('workOrders', 1)->where('workOrders.0.id', $legacy->id));
        $this->travelTo($future->planned_start_at);
        $this->get('/operator/queue?workstation=all')->assertInertia(fn (AssertableInertia $page) => $page->has('activeWorkOrders', 2));
        $this->get('/operator/workstation?workstation=all')->assertInertia(fn (AssertableInertia $page) => $page->has('workOrders', 2));
    }

    public function test_direct_operator_start_count_and_shift_entry_are_rejected_without_writes(): void
    {
        $order = $this->order();
        $shift = Shift::create(['line_id' => $this->line->id, 'name' => 'Morning', 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true]);
        $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
        foreach (['start' => [], 'complete' => ['produced_qty' => 1], 'shift-entry' => ['shift_id' => $shift->id, 'quantity' => 1]] as $action => $body) {
            $this->postJson('/operator/workstation/'.$order->id.'/'.$action, $body)
                ->assertUnprocessable()->assertJsonValidationErrors('planned_start_at');
        }
        $this->assertDatabaseCount('work_order_shift_entries', 0);
        $this->assertSame('PENDING', $order->fresh()->status);
        $this->assertEquals(0, $order->fresh()->produced_qty);
        $this->travelTo($order->planned_start_at);
        $this->post('/operator/workstation/'.$order->id.'/complete', ['produced_qty' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(1, $order->fresh()->produced_qty);
    }

    public function test_step_start_is_blocked_in_both_flow_modes_and_allowed_at_boundary(): void
    {
        foreach ([ProductionFlow::WHOLE_BATCH, ProductionFlow::TRANSFER] as $mode) {
            ProductionFlow::set($mode);
            $this->travelTo(Carbon::parse('2026-09-16 10:00:00'));
            $order = $this->order();
            $batch = Batch::factory()->create(['work_order_id' => $order->id, 'target_qty' => 10]);
            $step = BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'status' => BatchStep::STATUS_READY]);
            $this->assertFalse($step->canStart());
            try {
                app(BatchService::class)->startStep($step, $this->operator);
                $this->fail('Future production was started.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('planned_start_at', $e->errors());
            }
            $this->assertNull($step->fresh()->started_at);
            $this->travelTo($order->planned_start_at);
            app(BatchService::class)->startStep($step->fresh(), $this->operator);
            $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $step->fresh()->status);
        }
    }

    public function test_machine_counts_do_not_bypass_start(): void
    {
        $order = $this->order(['counting_source' => 'machine']);
        $service = app(MachineProductionService::class);
        $this->assertFalse($service->recordGoodCount($order, 1));
        $this->assertFalse($service->recordAbsoluteCount($order, 5));
        $this->assertEquals(0, $order->fresh()->produced_qty);
        $this->travelTo($order->planned_start_at);
        $this->assertTrue($service->recordGoodCount($order, 1));
        $this->assertEquals(1, $order->fresh()->produced_qty);
    }

    public function test_planner_moves_start_without_changing_deadline_and_board_includes_start_only_orders(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin)->putJson('/admin/schedule/'.$order->id, [
            'planned_start_at' => '2026-09-18T07:30', 'planned_end_at' => null, 'shift_number' => 1,
        ])->assertOk();
        $this->assertSame('2026-09-30', $order->fresh()->due_date->toDateString());
        $this->get('/admin/schedule?view_mode=daily&start_date=2026-09-18')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('workOrders', 1)->where('workOrders.0.id', $order->id)->has('backlogOrders', 0));
        $this->assertNotContains($order->id, WorkOrder::availableForProduction()->pluck('id')->all());
        $this->putJson('/admin/schedule/'.$order->id, ['planned_start_at' => '2026-09-16T09:00'])->assertOk();
        $this->assertContains($order->id, WorkOrder::availableForProduction()->pluck('id')->all());
    }

    public function test_started_order_cannot_be_postponed_and_request_is_atomic(): void
    {
        $order = $this->order(['planned_start_at' => null, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $this->actingAs($this->admin)->putJson('/admin/schedule/'.$order->id, [
            'planned_start_at' => '2026-09-17T12:00', 'due_date' => '2026-10-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('planned_start_at');
        $this->assertNull($order->fresh()->planned_start_at);
        $this->assertSame('2026-09-30', $order->fresh()->due_date->toDateString());
        $this->putJson('/admin/schedule/'.$order->id.'/resize', [
            'planned_start_at' => '2026-09-17T12:00', 'planned_end_at' => '2026-09-17T13:00',
        ])->assertUnprocessable();
    }

    public function test_invalid_dates_and_unauthorized_scheduler_edits_are_rejected(): void
    {
        $order = $this->order();
        $this->putJson('/admin/schedule/'.$order->id, ['planned_start_at' => 'invalid'])->assertUnauthorized();
        $this->actingAs($this->operator)->putJson('/admin/schedule/'.$order->id, ['planned_start_at' => null])->assertForbidden();
        $this->actingAs($this->admin)->putJson('/admin/schedule/'.$order->id, ['planned_start_at' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('planned_start_at');
        $this->putJson('/admin/schedule/'.$order->id, ['planned_start_at' => '2026-09-20T10:00', 'end_date' => '2026-09-19'])
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_capacity_drilldown_uses_start_instead_of_deadline(): void
    {
        $order = $this->order(['planned_start_at' => '2026-09-18 07:30:00']);
        $service = app(\App\Services\Schedule\CapacityService::class);
        $rows = $service->cellOrders($this->line->id, Carbon::parse('2026-09-18'), Carbon::parse('2026-09-18'));
        $this->assertContains($order->id, array_column($rows, 'id'));
        $this->assertSame([], $service->cellOrders($this->line->id, Carbon::parse('2026-09-30'), Carbon::parse('2026-09-30')));
    }

    public function test_api_persists_start_and_rejects_premature_transition(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin);
        $response = $this->postJson('/api/v1/work-orders', [
            'order_no' => 'API-START', 'planned_qty' => 10, 'line_id' => $this->line->id,
            'planned_start_at' => '2026-09-17T08:15',
        ])->assertCreated();
        $id = $response->json('data.id');
        $this->postJson('/api/v1/work-orders/'.$id.'/cancel')->assertOk();
        $this->postJson('/api/v1/work-orders/'.$id.'/reopen')->assertUnprocessable()->assertJsonValidationErrors('planned_start_at');
        $this->putJson('/api/v1/work-orders/'.$id, ['planned_start_at' => null])->assertOk();
        $this->assertNull(WorkOrder::findOrFail($id)->planned_start_at);
        $this->postJson('/api/v1/work-orders/'.$id.'/reopen')->assertOk();
    }

    public function test_bulk_reopen_skips_future_orders_without_blocking_legacy_orders(): void
    {
        $future = $this->order(['status' => WorkOrder::STATUS_CANCELLED]);
        $legacy = $this->order(['status' => WorkOrder::STATUS_CANCELLED, 'planned_start_at' => null]);
        \App\Services\WorkOrder\WorkOrderService::applyBulkTransition([$future->id, $legacy->id], 'reopen');
        $this->assertSame(WorkOrder::STATUS_CANCELLED, $future->fresh()->status);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $legacy->fresh()->status);
    }

    public function test_undo_cannot_restore_a_future_start_after_production_begins(): void
    {
        $order = $this->order();
        $planner = app(\App\Services\Schedule\SchedulePlannerService::class);
        $planner->updateOrder($order, ['planned_start_at' => '2026-09-16T09:00']);
        $change = \App\Models\ScheduleChangeLog::where('work_order_id', $order->id)->firstOrFail();
        $order->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        try {
            $planner->undoChange($change);
            $this->fail('Undo postponed running production.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('planned_start_at', $e->errors());
        }
        $this->assertNull($change->fresh()->undone_at);
        $this->assertSame('09:00', $order->fresh()->planned_start_at->format('H:i'));
    }
}
