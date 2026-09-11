<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\UnitStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Operator-facing unit-level (serial) execution (#290), Phase 2 — the web
 * session/line-selection counterpart to Tests\Feature\Api\UnitStepTest.
 */
class UnitStepWebTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    /** A Batch created from a Unit-mode ProcessTemplate. */
    private function makeUnitModeBatch(): Batch
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()
            ->withSteps(3)
            ->create(['product_type_id' => $productType->id, 'execution_mode' => ProcessTemplate::EXECUTION_MODE_UNIT]);

        $workOrder = WorkOrder::factory()->create([
            'product_type_id' => $productType->id,
            'process_snapshot' => $template->toSnapshot(),
        ]);

        return app(WorkOrderService::class)->createBatch($workOrder, 5);
    }

    private function actingOperator(Batch $batch)
    {
        return $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $batch->workOrder->line_id]);
    }

    public function test_operator_can_register_a_unit(): void
    {
        $batch = $this->makeUnitModeBatch();

        $this->actingOperator($batch)
            ->post('/operator/unit/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('serial_units', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);
        $this->assertDatabaseHas('unit_steps', ['batch_id' => $batch->id, 'step_number' => 1, 'status' => UnitStep::STATUS_READY]);
    }

    public function test_register_requires_a_batch_id(): void
    {
        $batch = $this->makeUnitModeBatch();

        $this->actingOperator($batch)
            ->post('/operator/unit/register', [])
            ->assertSessionHasErrors('batch_id');
    }

    public function test_operator_from_different_line_cannot_register_a_unit(): void
    {
        $batch = $this->makeUnitModeBatch();

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $batch->workOrder->line_id + 999])
            ->post('/operator/unit/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001'])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('serial_units', ['batch_id' => $batch->id]);
    }

    public function test_register_on_batch_mode_batch_is_rejected(): void
    {
        $workOrder = WorkOrder::factory()->create(); // default template is batch mode
        $batch = app(WorkOrderService::class)->createBatch($workOrder, 5);

        $this->actingOperator($batch)
            ->post('/operator/unit/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001'])
            ->assertSessionHas('error', __('This batch is not running in unit execution mode.'));

        $this->assertDatabaseMissing('serial_units', ['batch_id' => $batch->id]);
    }

    public function test_guest_cannot_register_a_unit(): void
    {
        $batch = $this->makeUnitModeBatch();

        $this->post('/operator/unit/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001'])
            ->assertRedirect(route('login'));
    }

    public function test_operator_can_start_and_complete_first_unit_step(): void
    {
        $batch = $this->makeUnitModeBatch();
        $this->actingOperator($batch)->post('/operator/unit/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);
        $first = UnitStep::where('batch_id', $batch->id)->where('step_number', 1)->firstOrFail();

        $this->actingOperator($batch)
            ->post("/operator/unit-step/{$first->id}/start")
            ->assertSessionHas('success');
        $this->assertSame(UnitStep::STATUS_IN_PROGRESS, $first->fresh()->status);

        $this->actingOperator($batch)
            ->post("/operator/unit-step/{$first->id}/complete")
            ->assertSessionHas('success');
        $this->assertSame(UnitStep::STATUS_DONE, $first->fresh()->status);

        $second = UnitStep::where('batch_id', $batch->id)->where('step_number', 2)->firstOrFail();
        $this->assertSame(UnitStep::STATUS_READY, $second->status);
    }

    public function test_cannot_start_second_unit_step_before_first_done(): void
    {
        $batch = $this->makeUnitModeBatch();
        $this->actingOperator($batch)->post('/operator/unit/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);
        $second = UnitStep::where('batch_id', $batch->id)->where('step_number', 2)->firstOrFail();

        $this->actingOperator($batch)
            ->post("/operator/unit-step/{$second->id}/start")
            ->assertSessionHas('error');

        $this->assertSame(UnitStep::STATUS_PENDING, $second->fresh()->status);
    }

    public function test_operator_from_different_line_cannot_start_a_unit_step(): void
    {
        $batch = $this->makeUnitModeBatch();
        $this->actingOperator($batch)->post('/operator/unit/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);
        $first = UnitStep::where('batch_id', $batch->id)->where('step_number', 1)->firstOrFail();

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $batch->workOrder->line_id + 999])
            ->post("/operator/unit-step/{$first->id}/start")
            ->assertSessionHas('error', __('This step does not belong to the selected line.'));

        $this->assertSame(UnitStep::STATUS_READY, $first->fresh()->status);
    }

    public function test_guest_cannot_start_a_unit_step(): void
    {
        $batch = $this->makeUnitModeBatch();
        // Register via the service (not an authenticated HTTP call) — actingAs()
        // persists on the TestCase for the rest of the test method, so an earlier
        // authenticated request here would leak into this "guest" assertion.
        $unit = app(\App\Services\Unit\UnitProgressionService::class)->registerUnit($batch, 'SN-001');
        $first = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 1)->firstOrFail();

        $this->post("/operator/unit-step/{$first->id}/start")
            ->assertRedirect(route('login'));
    }

    public function test_work_order_detail_page_exposes_unit_mode_batches(): void
    {
        $batch = $this->makeUnitModeBatch();
        $this->actingOperator($batch)->post('/operator/unit/register', ['batch_id' => $batch->id, 'serial_no' => 'SN-001']);

        $response = $this->actingOperator($batch)->get("/operator/work-order/{$batch->work_order_id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('operator/WorkOrderDetail')
            ->where('workOrder.process_snapshot.execution_mode', 'unit')
            ->where('workOrder.batches.0.serial_units.0.serial_no', 'SN-001'));
    }
}
