<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
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
