<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Traceability\SerialTraceService;
use App\Services\Traceability\TraceabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TraceabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        $operatorRole = Role::create(['name' => 'Operator', 'guard_name' => 'web']);
        foreach (['view work orders'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }
        $adminRole->givePermissionTo(Permission::all());

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    /**
     * Build: a finished batch (lot FG-1) that consumed material lot RAW-1 at step 1.
     */
    private function scenario(): array
    {
        $material = Material::factory()->create(['name' => 'Steel Sheet', 'code' => 'STL']);
        $rawLot = MaterialLot::factory()->create([
            'lot_number' => 'RAW-1',
            'material_id' => $material->id,
            'supplier_lot_no' => 'SUPP-9',
            'source_container_no' => 'CONT-7',
        ]);

        $wo = WorkOrder::factory()->create(['order_no' => 'WO-TRACE-1', 'status' => WorkOrder::STATUS_DONE]);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'lot_number' => 'FG-1',
            'status' => Batch::STATUS_DONE,
        ]);
        $step = BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 1,
            'status' => BatchStep::STATUS_DONE,
            'completed_by_id' => $this->operator->id,
            'completed_at' => now(),
        ]);

        BatchStepLotConsumption::create([
            'batch_step_id' => $step->id,
            'material_lot_id' => $rawLot->id,
            'quantity_consumed' => 5,
            'consumed_at' => now(),
            'recorded_by_id' => $this->operator->id,
        ]);

        return compact('material', 'rawLot', 'wo', 'batch', 'step');
    }

    // ── Phase 1: TraceabilityService ─────────────────────────────────────

    public function test_forward_trace_finds_work_orders_that_consumed_lot(): void
    {
        $s = $this->scenario();
        $result = app(TraceabilityService::class)->forwardTrace($s['rawLot']);

        $this->assertEquals(5.0, $result['total_consumed']);
        $this->assertCount(1, $result['work_orders']);
        $this->assertEquals('WO-TRACE-1', $result['work_orders']->first()->order_no);
    }

    public function test_backward_trace_from_finished_batch_lists_ingredient_lots(): void
    {
        $s = $this->scenario();
        $gen = app(TraceabilityService::class)->batchGenealogy($s['batch']);

        $this->assertCount(1, $gen['distinct_input_lots']);
        $this->assertEquals('RAW-1', $gen['distinct_input_lots']->first()->lot_number);
    }

    public function test_backward_trace_follows_source_batch_link(): void
    {
        $s = $this->scenario();
        // A semi-finished lot produced BY the batch, linked via the formal FK.
        $semi = MaterialLot::factory()->create([
            'lot_number' => 'SEMI-1',
            'source_batch_id' => $s['batch']->id,
        ]);

        $tree = app(TraceabilityService::class)->backwardTraceLot($semi);

        $this->assertEquals($s['batch']->id, $tree['source_batch_id']);
        // Ingredients of SEMI-1 = lots consumed by its source batch = RAW-1
        $this->assertCount(1, $tree['ingredients']);
        $this->assertEquals('RAW-1', $tree['ingredients'][0]['lot']['lot_number']);
    }

    public function test_resolve_matches_finished_lot_material_lot_and_supplier_lot(): void
    {
        $s = $this->scenario();
        $svc = app(TraceabilityService::class);

        $this->assertEquals('batch', $svc->resolve('FG-1')['type']);
        $this->assertEquals('material_lot', $svc->resolve('RAW-1')['type']);
        $this->assertEquals('material_lot', $svc->resolve('SUPP-9')['type']);
        $this->assertNull($svc->resolve('DOES-NOT-EXIST'));
    }

    public function test_resolve_matches_source_container_no(): void
    {
        $s = $this->scenario();

        $resolved = app(TraceabilityService::class)->resolve('CONT-7');

        $this->assertNotNull($resolved);
        $this->assertEquals('material_lot', $resolved['type']);
        $this->assertEquals($s['rawLot']->id, $resolved['model']->id);
    }

    public function test_resolve_does_not_match_soft_deleted_lots(): void
    {
        $s = $this->scenario();
        $this->actingAs($this->admin);
        $s['rawLot']->delete();

        $svc = app(TraceabilityService::class);

        // None of the alternate identifiers may leak the trashed lot — the
        // orWhere chain is grouped so it can't escape the soft-delete scope.
        $this->assertNull($svc->resolve('RAW-1'));
        $this->assertNull($svc->resolve('SUPP-9'));
        $this->assertNull($svc->resolve('CONT-7'));
    }

    public function test_genealogy_payloads_expose_source_container_no(): void
    {
        $s = $this->scenario();
        $svc = app(TraceabilityService::class);

        // Backward node of the raw lot carries the scanned container.
        $node = $svc->backwardTraceLot($s['rawLot']);
        $this->assertEquals('CONT-7', $node['source_container_no']);

        // Forward trace exposes it on the lot summary.
        $forward = $svc->forwardTrace($s['rawLot']);
        $this->assertEquals('CONT-7', $forward['lot']['source_container_no']);

        // Batch genealogy's distinct input lots include it too.
        $gen = $svc->batchGenealogy($s['batch']);
        $this->assertEquals('CONT-7', $gen['distinct_input_lots']->first()->source_container_no);
    }

    // ── Recall: reverse traceability by component lot / serial ───────────

    public function test_recall_impact_lists_affected_work_orders_and_finished_units(): void
    {
        $s = $this->scenario();
        // A finished unit produced under the consuming work order — the recall target.
        SerialUnit::factory()->create([
            'serial_no' => 'FG-UNIT-1',
            'work_order_id' => $s['wo']->id,
            'status' => SerialUnit::STATUS_SHIPPED,
        ]);

        $impact = app(TraceabilityService::class)->recallImpact(collect([$s['rawLot']]));

        $this->assertCount(1, $impact['work_orders']);
        $this->assertEquals('WO-TRACE-1', $impact['work_orders'][0]['order_no']);
        $this->assertEquals(5.0, $impact['work_orders'][0]['quantity_consumed']);
        $this->assertEquals(['FG-UNIT-1'], array_column($impact['work_orders'][0]['finished_serials'], 'serial_no'));
        $this->assertEquals(1, $impact['totals']['work_orders']);
        $this->assertEquals(1, $impact['totals']['finished_serials']);
        $this->assertFalse($impact['truncated']);
    }

    public function test_recall_impact_walks_transitively_through_semi_finished_output(): void
    {
        $s = $this->scenario();
        // FG-1's batch produces a semi-finished lot, consumed by a downstream WO.
        $semi = MaterialLot::factory()->create([
            'lot_number' => 'SEMI-1',
            'source_batch_id' => $s['batch']->id,
        ]);
        $downWo = WorkOrder::factory()->create(['order_no' => 'WO-DOWN', 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $downBatch = Batch::factory()->create(['work_order_id' => $downWo->id, 'lot_number' => 'FG-2']);
        $downStep = BatchStep::factory()->create(['batch_id' => $downBatch->id, 'step_number' => 1]);
        BatchStepLotConsumption::create([
            'batch_step_id' => $downStep->id,
            'material_lot_id' => $semi->id,
            'quantity_consumed' => 2,
            'consumed_at' => now(),
            'recorded_by_id' => $this->operator->id,
        ]);

        $impact = app(TraceabilityService::class)->recallImpact(collect([$s['rawLot']]));

        $orderNos = array_column($impact['work_orders'], 'order_no');
        $this->assertContains('WO-TRACE-1', $orderNos);
        $this->assertContains('WO-DOWN', $orderNos);
        $this->assertEquals(2, $impact['totals']['work_orders']);
    }

    public function test_recall_impact_for_serial_resolves_via_its_batch_output(): void
    {
        $s = $this->scenario();
        // A semi-finished lot produced by the serial's batch, consumed downstream.
        $semi = MaterialLot::factory()->create(['lot_number' => 'SEMI-2', 'source_batch_id' => $s['batch']->id]);
        $downWo = WorkOrder::factory()->create(['order_no' => 'WO-DOWN-2', 'status' => WorkOrder::STATUS_DONE]);
        $downBatch = Batch::factory()->create(['work_order_id' => $downWo->id, 'lot_number' => 'FG-3']);
        $downStep = BatchStep::factory()->create(['batch_id' => $downBatch->id, 'step_number' => 1]);
        BatchStepLotConsumption::create([
            'batch_step_id' => $downStep->id,
            'material_lot_id' => $semi->id,
            'quantity_consumed' => 3,
            'consumed_at' => now(),
            'recorded_by_id' => $this->operator->id,
        ]);
        // The component serial, produced by FG-1's batch.
        $unit = SerialUnit::factory()->create(['serial_no' => 'COMP-9', 'batch_id' => $s['batch']->id]);

        $impact = app(TraceabilityService::class)->recallImpactForSerial($unit);

        $this->assertEquals(['WO-DOWN-2'], array_column($impact['work_orders'], 'order_no'));
    }

    public function test_recall_impact_for_serial_without_downstream_is_empty(): void
    {
        $s = $this->scenario();
        $unit = SerialUnit::factory()->create(['serial_no' => 'COMP-ORPHAN', 'batch_id' => $s['batch']->id]);

        $impact = app(TraceabilityService::class)->recallImpactForSerial($unit);

        $this->assertSame([], $impact['work_orders']);
        $this->assertEquals(0, $impact['totals']['finished_serials']);
    }

    public function test_console_shows_recall_impact_for_material_lot(): void
    {
        $s = $this->scenario();
        SerialUnit::factory()->create(['serial_no' => 'FG-UNIT-2', 'work_order_id' => $s['wo']->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.traceability.index', ['q' => 'RAW-1']))
            ->assertOk()
            ->assertSee('FG-UNIT-2');
    }

    // ── Diagnostic drill-down: finished unit → component → lines ─────────

    /**
     * Build a finished serial unit (a "shoe") whose final batch consumed a
     * semi-finished component (COMP-1) produced on its own line (Sole Line).
     *
     * @return array{unit: SerialUnit, line: Line, componentLot: MaterialLot}
     */
    private function componentScenario(): array
    {
        $line = Line::factory()->create(['name' => 'Sole Line', 'code' => 'L-SOLE']);
        $ws = Workstation::factory()->create(['line_id' => $line->id, 'name' => 'Molding']);

        $compWo = WorkOrder::factory()->create(['order_no' => 'WO-COMP']);
        $compBatch = Batch::factory()->create(['work_order_id' => $compWo->id, 'lot_number' => 'COMP-1-LOT']);
        BatchStep::factory()->create([
            'batch_id' => $compBatch->id,
            'step_number' => 1,
            'name' => 'Mold sole',
            'status' => BatchStep::STATUS_DONE,
            'workstation_id' => $ws->id,
            'completed_by_id' => $this->operator->id,
            'completed_at' => now(),
        ]);
        $componentLot = MaterialLot::factory()->create(['lot_number' => 'COMP-1', 'source_batch_id' => $compBatch->id]);

        $finalWo = WorkOrder::factory()->create(['order_no' => 'WO-SHOE']);
        $finalBatch = Batch::factory()->create(['work_order_id' => $finalWo->id, 'lot_number' => 'SHOE-1']);
        $finalStep = BatchStep::factory()->create(['batch_id' => $finalBatch->id, 'step_number' => 1]);
        BatchStepLotConsumption::create([
            'batch_step_id' => $finalStep->id,
            'material_lot_id' => $componentLot->id,
            'quantity_consumed' => 2,
            'consumed_at' => now(),
            'recorded_by_id' => $this->operator->id,
        ]);
        $unit = SerialUnit::factory()->create(['serial_no' => 'FG-SHOE', 'batch_id' => $finalBatch->id]);

        return compact('unit', 'line', 'componentLot');
    }

    public function test_component_line_journeys_show_lines_each_component_passed_through(): void
    {
        $c = $this->componentScenario();

        $result = app(TraceabilityService::class)->componentLineJourneys($c['unit']);

        $this->assertCount(1, $result['components']);
        $comp = $result['components'][0];
        $this->assertEquals('COMP-1', $comp['lot_number']);
        $this->assertFalse($comp['is_raw']);
        $this->assertEquals(['Sole Line'], array_column($comp['lines'], 'name'));
        $this->assertEquals('Mold sole', $comp['steps'][0]['name']);
        $this->assertEquals('Sole Line', $comp['steps'][0]['line']);
        $this->assertEquals('Molding', $comp['steps'][0]['workstation']);
    }

    public function test_component_line_journeys_mark_raw_supplied_lots_with_no_line(): void
    {
        $s = $this->scenario();
        $unit = SerialUnit::factory()->create(['serial_no' => 'FG-RAW', 'batch_id' => $s['batch']->id]);

        $result = app(TraceabilityService::class)->componentLineJourneys($unit);

        $this->assertCount(1, $result['components']);
        $this->assertEquals('RAW-1', $result['components'][0]['lot_number']);
        $this->assertTrue($result['components'][0]['is_raw']);
        $this->assertSame([], $result['components'][0]['lines']);
        $this->assertSame([], $result['components'][0]['steps']);
    }

    public function test_component_line_journeys_empty_for_unit_without_batch(): void
    {
        $unit = SerialUnit::factory()->create(['serial_no' => 'NO-BATCH', 'batch_id' => null]);

        $result = app(TraceabilityService::class)->componentLineJourneys($unit);

        $this->assertSame([], $result['components']);
    }

    public function test_console_shows_component_lines_for_serial(): void
    {
        $this->componentScenario();

        $this->actingAs($this->admin)
            ->get(route('admin.traceability.index', ['q' => 'FG-SHOE']))
            ->assertOk()
            ->assertSee('COMP-1')
            ->assertSee('Sole Line');
    }

    // ── Phase 2: Traceability console ────────────────────────────────────

    public function test_traceability_page_requires_admin(): void
    {
        $this->actingAs($this->operator)
            ->get(route('admin.traceability.index'))
            ->assertForbidden();
    }

    public function test_admin_can_trace_finished_lot(): void
    {
        $this->scenario();
        $this->actingAs($this->admin)
            ->get(route('admin.traceability.index', ['q' => 'FG-1']))
            ->assertOk()
            ->assertSee('FG-1')
            ->assertSee('RAW-1');
    }

    public function test_unknown_search_term_shows_no_result(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.traceability.index', ['q' => 'NOPE-123']))
            ->assertOk()
            ->assertSee('NOPE-123');
    }

    // ── Phase 3: Serial genealogy ────────────────────────────────────────

    public function test_register_unit_and_record_step_history(): void
    {
        $s = $this->scenario();
        $ws = Workstation::factory()->create(['name' => 'CNC-1', 'line_id' => \App\Models\Line::factory()]);
        $svc = app(SerialTraceService::class);

        $unit = $svc->registerUnit('SN-100', ['work_order_id' => $s['wo']->id, 'batch_id' => $s['batch']->id]);
        $this->assertDatabaseHas('serial_units', ['serial_no' => 'SN-100']);

        $svc->recordStep($unit, $this->operator, $s['step'], [
            'workstation_id' => $ws->id,
            'parameters' => ['temp' => 210, 'pressure' => 4.2],
            'result' => 'pass',
        ]);

        $history = $svc->getHistory($unit->fresh());
        $this->assertCount(1, $history->history);
        $this->assertEquals(210, $history->history->first()->parameters['temp']);
    }

    public function test_failed_step_scraps_the_unit(): void
    {
        $svc = app(SerialTraceService::class);
        $unit = $svc->registerUnit('SN-FAIL');

        $svc->recordStep($unit, $this->operator, null, ['result' => 'fail']);

        $this->assertEquals(SerialUnit::STATUS_SCRAPPED, $unit->fresh()->status);
    }

    public function test_serial_lookup_in_traceability_console(): void
    {
        $svc = app(SerialTraceService::class);
        $svc->registerUnit('SN-LOOKUP');

        $this->actingAs($this->admin)
            ->get(route('admin.traceability.index', ['q' => 'SN-LOOKUP']))
            ->assertOk()
            ->assertSee('SN-LOOKUP');
    }

    public function test_api_register_serial_unit(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/serial-units', ['serial_no' => 'SN-API-1'])
            ->assertCreated()
            ->assertJsonPath('data.serial_no', 'SN-API-1');
    }
}
