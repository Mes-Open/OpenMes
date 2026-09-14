<?php

namespace Tests\Unit\Services;

use App\Models\Batch;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\SerialSequence;
use App\Models\SerialUnit;
use App\Models\UnitStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Unit\UnitProgressionService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitProgressionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UnitProgressionService $service;

    protected WorkOrder $workOrder;

    protected Batch $unitModeBatch;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UnitProgressionService::class);
        $this->user = User::factory()->create();

        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()
            ->withSteps(3)
            ->create(['product_type_id' => $productType->id, 'execution_mode' => ProcessTemplate::EXECUTION_MODE_UNIT]);

        $this->workOrder = WorkOrder::factory()->create([
            'product_type_id' => $productType->id,
            'process_snapshot' => $template->toSnapshot(),
        ]);

        $this->unitModeBatch = app(WorkOrderService::class)->createBatch($this->workOrder, 5);
    }

    // ── registerUnit() ───────────────────────────────────────────────────────

    public function test_register_unit_with_explicit_serial_creates_pipeline(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch, 'SN-EXPLICIT-001');

        $this->assertEquals('SN-EXPLICIT-001', $unit->serial_no);
        $this->assertEquals(SerialUnit::STATUS_IN_PRODUCTION, $unit->status);

        $steps = UnitStep::where('serial_unit_id', $unit->id)->orderBy('step_number')->get();
        $this->assertCount(3, $steps);
        $this->assertEquals(UnitStep::STATUS_READY, $steps[0]->status);
        $this->assertEquals(UnitStep::STATUS_PENDING, $steps[1]->status);
        $this->assertEquals(UnitStep::STATUS_PENDING, $steps[2]->status);
    }

    public function test_register_unit_with_auto_generate_and_no_sequence_throws(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No serial sequence configured');

        $this->service->registerUnit($this->unitModeBatch, null, autoGenerate: true);
    }

    public function test_register_unit_auto_generates_from_configured_sequence(): void
    {
        SerialSequence::factory()->create(['prefix' => 'SN', 'pad_size' => 3]);

        $unit = $this->service->registerUnit($this->unitModeBatch, null, autoGenerate: true);

        $this->assertStringStartsWith('SN-', $unit->serial_no);
    }

    // ── Serialize later (#290 known-bugs item 3) ─────────────────────────────

    public function test_register_unit_without_serial_or_auto_generate_creates_unserialized_unit(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch);

        $this->assertNull($unit->serial_no);
        $this->assertFalse($unit->isSerialized());
    }

    public function test_unserialized_unit_can_start_and_complete_steps(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch);
        $first = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 1)->first();

        $this->service->startUnitStep($first, $this->user);
        $this->service->completeUnitStep($first, $this->user);

        $second = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 2)->first();
        $this->assertEquals(UnitStep::STATUS_READY, $second->fresh()->status);
        $this->assertNull($unit->fresh()->serial_no);
    }

    public function test_assign_serial_attaches_a_serial_to_an_unserialized_unit(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch);

        $updated = $this->service->assignSerial($unit, 'SN-LATE-001');

        $this->assertEquals('SN-LATE-001', $updated->serial_no);
        $this->assertTrue($updated->isSerialized());
    }

    public function test_assign_serial_works_after_a_few_steps_are_already_done(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch);
        $first = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 1)->first();
        $this->service->startUnitStep($first, $this->user);
        $this->service->completeUnitStep($first, $this->user);

        $updated = $this->service->assignSerial($unit->fresh(), 'SN-MIDWAY-001');

        $this->assertEquals('SN-MIDWAY-001', $updated->serial_no);
        // The steps already done stay done — assigning a serial doesn't touch progression.
        $this->assertEquals(UnitStep::STATUS_DONE, $first->fresh()->status);
    }

    public function test_assign_serial_via_auto_generate(): void
    {
        SerialSequence::factory()->create(['prefix' => 'SN', 'pad_size' => 3]);
        $unit = $this->service->registerUnit($this->unitModeBatch);

        $updated = $this->service->assignSerial($unit, null, autoGenerate: true);

        $this->assertStringStartsWith('SN-', $updated->serial_no);
    }

    public function test_assign_serial_twice_throws(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch);
        $this->service->assignSerial($unit, 'SN-ONCE-001');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already has a serial number');

        $this->service->assignSerial($unit->fresh(), 'SN-TWICE-001');
    }

    public function test_assign_serial_without_serial_or_auto_generate_throws(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Provide a serial number or choose auto-generate');

        $this->service->assignSerial($unit);
    }

    public function test_assign_serial_rejects_a_serial_already_in_use(): void
    {
        $existing = $this->service->registerUnit($this->unitModeBatch, 'SN-TAKEN-001');
        $unassigned = $this->service->registerUnit($this->unitModeBatch);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already in use');

        $this->service->assignSerial($unassigned, $existing->serial_no);
    }

    public function test_register_unit_on_batch_mode_batch_throws(): void
    {
        $batchModeWorkOrder = WorkOrder::factory()->create(); // default template is batch mode
        $batchModeBatch = app(WorkOrderService::class)->createBatch($batchModeWorkOrder, 5);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not running in unit execution mode');

        $this->service->registerUnit($batchModeBatch, 'SN-SHOULD-FAIL');
    }

    // ── startUnitStep() / completeUnitStep() interlock ──────────────────────

    public function test_start_second_unit_step_before_first_done_throws(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch, 'SN-001');
        $second = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 2)->first();

        $this->expectException(\Exception::class);

        $this->service->startUnitStep($second, $this->user);
    }

    public function test_complete_first_step_promotes_second_to_ready(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch, 'SN-001');
        $first = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 1)->first();

        $this->service->startUnitStep($first, $this->user);
        $this->service->completeUnitStep($first, $this->user);

        $second = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 2)->first();
        $this->assertEquals(UnitStep::STATUS_READY, $second->fresh()->status);
    }

    public function test_complete_step_records_who_and_when(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch, 'SN-001');
        $first = UnitStep::where('serial_unit_id', $unit->id)->where('step_number', 1)->first();

        $this->service->startUnitStep($first, $this->user);
        $this->service->completeUnitStep($first, $this->user);

        $fresh = $first->fresh();
        $this->assertEquals(UnitStep::STATUS_DONE, $fresh->status);
        $this->assertEquals($this->user->id, $fresh->completed_by_id);
        $this->assertNotNull($fresh->completed_at);
    }

    // ── The core pipelining claim ────────────────────────────────────────────

    public function test_two_units_can_sit_at_different_steps_simultaneously(): void
    {
        $unitA = $this->service->registerUnit($this->unitModeBatch, 'SN-A');
        $unitB = $this->service->registerUnit($this->unitModeBatch, 'SN-B');

        // Unit A finishes step 1 and moves on to step 2...
        $aStep1 = UnitStep::where('serial_unit_id', $unitA->id)->where('step_number', 1)->first();
        $this->service->startUnitStep($aStep1, $this->user);
        $this->service->completeUnitStep($aStep1, $this->user);
        $aStep2 = UnitStep::where('serial_unit_id', $unitA->id)->where('step_number', 2)->first();
        $this->service->startUnitStep($aStep2, $this->user);

        // ...while unit B is only just starting step 1. Neither blocks the other.
        $bStep1 = UnitStep::where('serial_unit_id', $unitB->id)->where('step_number', 1)->first();
        $this->service->startUnitStep($bStep1, $this->user);

        $this->assertEquals(UnitStep::STATUS_IN_PROGRESS, $aStep2->fresh()->status);
        $this->assertEquals(UnitStep::STATUS_IN_PROGRESS, $bStep1->fresh()->status);
        $this->assertEquals(UnitStep::STATUS_DONE, $aStep1->fresh()->status);
    }

    // ── Serial + batch completion (scope doc decision #4) ────────────────────

    public function test_serial_unit_marked_completed_once_all_steps_done(): void
    {
        $unit = $this->service->registerUnit($this->unitModeBatch, 'SN-001');
        $this->completeAllSteps($unit);

        $this->assertEquals(SerialUnit::STATUS_COMPLETED, $unit->fresh()->status);
    }

    public function test_batch_stays_in_progress_until_every_unit_completes(): void
    {
        $unitA = $this->service->registerUnit($this->unitModeBatch, 'SN-A');
        $this->service->registerUnit($this->unitModeBatch, 'SN-B'); // never progressed

        $this->completeAllSteps($unitA);

        $this->assertEquals(Batch::STATUS_IN_PROGRESS, $this->unitModeBatch->fresh()->status);
    }

    public function test_batch_marked_done_only_once_every_unit_completes(): void
    {
        $unitA = $this->service->registerUnit($this->unitModeBatch, 'SN-A');
        $unitB = $this->service->registerUnit($this->unitModeBatch, 'SN-B');

        $this->completeAllSteps($unitA);
        $this->assertEquals(Batch::STATUS_IN_PROGRESS, $this->unitModeBatch->fresh()->status);

        $this->completeAllSteps($unitB);

        $fresh = $this->unitModeBatch->fresh();
        $this->assertEquals(Batch::STATUS_DONE, $fresh->status);
        $this->assertEquals(2, $fresh->produced_qty);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_work_order_produced_qty_reflects_completed_batch(): void
    {
        $unitA = $this->service->registerUnit($this->unitModeBatch, 'SN-A');
        $this->completeAllSteps($unitA);

        $this->assertEquals(1, $this->workOrder->fresh()->produced_qty);
    }

    private function completeAllSteps(SerialUnit $unit): void
    {
        $steps = UnitStep::where('serial_unit_id', $unit->id)->orderBy('step_number')->get();
        foreach ($steps as $step) {
            $this->service->startUnitStep($step->fresh(), $this->user);
            $this->service->completeUnitStep($step->fresh(), $this->user);
        }
    }
}
