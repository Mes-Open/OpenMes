<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\QualityCheck;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Traceability\TraceabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tracing by pallet number / customer order: pallet → batch → lots → machine →
 * operator → quality controls, plus the customer_order_no work-order field.
 */
class PalletTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);
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
     * Build a finished batch on a line/workstation that consumed RAW-1, passed a
     * quality control, and a pallet linked to that batch.
     */
    private function scenario(string $customerOrderNo = 'PO-555'): array
    {
        $material = Material::factory()->create(['name' => 'Steel Sheet', 'code' => 'STL']);
        $rawLot = MaterialLot::factory()->create(['lot_number' => 'RAW-1', 'material_id' => $material->id]);

        $line = Line::factory()->create(['name' => 'Line A']);
        $workstation = Workstation::factory()->create(['name' => 'Press 1', 'line_id' => $line->id]);

        $wo = WorkOrder::factory()->create([
            'order_no' => 'WO-PT-1',
            'customer_order_no' => $customerOrderNo,
            'status' => WorkOrder::STATUS_DONE,
        ]);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'lot_number' => 'FG-9',
            'status' => Batch::STATUS_DONE,
            'workstation_id' => $workstation->id,
        ]);
        $step = BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 1,
            'name' => 'Press',
            'status' => BatchStep::STATUS_DONE,
            'workstation_id' => $workstation->id,
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
        QualityCheck::create([
            'batch_id' => $batch->id,
            'checked_by' => $this->operator->id,
            'checked_at' => now(),
            'all_passed' => true,
        ]);

        $pallet = Pallet::factory()->create(['work_order_id' => $wo->id, 'batch_id' => $batch->id]);

        return compact('material', 'rawLot', 'line', 'workstation', 'wo', 'batch', 'step', 'pallet');
    }

    public function test_resolve_matches_pallet_number(): void
    {
        $s = $this->scenario();
        $resolved = app(TraceabilityService::class)->resolve($s['pallet']->pallet_no);

        $this->assertSame('pallet', $resolved['type']);
        $this->assertTrue($resolved['model']->is($s['pallet']));
    }

    public function test_pallet_trace_returns_full_chain(): void
    {
        $s = $this->scenario();
        $data = app(TraceabilityService::class)->palletTrace($s['pallet']->fresh());

        $this->assertSame($s['pallet']->pallet_no, $data['pallet']['pallet_no']);
        $this->assertSame('PO-555', $data['work_order']['customer_order_no']);
        $this->assertNotNull($data['batch']);
        $this->assertSame('FG-9', $data['batch']['lot_number']);

        $step = $data['batch']['steps'][0];
        $this->assertSame('Press 1', $step['machine']);
        $this->assertSame('Line A', $step['line']);
        $this->assertSame($this->operator->name, $step['operator']);
        $this->assertSame('RAW-1', $step['consumptions'][0]['lot_number']);

        $this->assertCount(1, $data['batch']['quality_checks']);
        $this->assertTrue($data['batch']['quality_checks'][0]['all_passed']);
    }

    public function test_pallet_trace_resolves_a_soft_deleted_batch(): void
    {
        // Recall must survive batch deletion: a pallet linked to a soft-deleted
        // batch should still resolve its genealogy, not show "not linked".
        $s = $this->scenario();
        $s['batch']->delete();

        $data = app(TraceabilityService::class)->palletTrace($s['pallet']->fresh());

        $this->assertNotNull($data['batch']);
        $this->assertSame('FG-9', $data['batch']['lot_number']);
    }

    public function test_pallet_trace_without_batch_is_null(): void
    {
        $wo = WorkOrder::factory()->create(['customer_order_no' => 'PO-1']);
        $pallet = Pallet::factory()->create(['work_order_id' => $wo->id, 'batch_id' => null]);

        $data = app(TraceabilityService::class)->palletTrace($pallet);

        $this->assertNull($data['batch']);
        $this->assertSame($pallet->pallet_no, $data['pallet']['pallet_no']);
    }

    public function test_customer_order_trace_aggregates_all_matching_work_orders(): void
    {
        $s = $this->scenario('PO-DUP');
        // Second work order under the same customer order.
        $wo2 = WorkOrder::factory()->create(['order_no' => 'WO-PT-2', 'customer_order_no' => 'PO-DUP']);
        Batch::factory()->create(['work_order_id' => $wo2->id, 'lot_number' => 'FG-2']);
        Pallet::factory()->create(['work_order_id' => $wo2->id]);

        $data = app(TraceabilityService::class)->customerOrderTrace('PO-DUP');

        $this->assertSame('PO-DUP', $data['customer_order_no']);
        $this->assertCount(2, $data['work_orders']);
        $orderNos = collect($data['work_orders'])->pluck('order_no')->all();
        $this->assertContains('WO-PT-1', $orderNos);
        $this->assertContains('WO-PT-2', $orderNos);
    }

    public function test_console_traces_a_pallet_end_to_end(): void
    {
        $s = $this->scenario();

        $this->actingAs($this->admin)
            ->get(route('admin.traceability.index', ['q' => $s['pallet']->pallet_no]))
            ->assertOk()
            ->assertSee($s['pallet']->pallet_no)
            ->assertSee('FG-9')
            ->assertSee('RAW-1')
            ->assertSee('Press 1');
    }

    public function test_console_traces_a_customer_order(): void
    {
        $this->scenario('PO-CONSOLE');

        $this->actingAs($this->admin)
            ->get(route('admin.traceability.index', ['q' => 'PO-CONSOLE']))
            ->assertOk()
            ->assertSee('PO-CONSOLE')
            ->assertSee('WO-PT-1');
    }

    public function test_work_order_store_accepts_customer_order_no(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.work-orders.store'), [
                'order_no' => 'WO-NEW-1',
                'customer_order_no' => 'CUST-77',
                'planned_qty' => 10,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('work_orders', [
            'order_no' => 'WO-NEW-1',
            'customer_order_no' => 'CUST-77',
        ]);
    }

    public function test_create_pallet_auto_links_when_work_order_has_one_batch(): void
    {
        $wo = WorkOrder::factory()->create();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);

        $this->actingAs($this->admin)
            ->postJson(route('packaging.pallets.create'), ['work_order_id' => $wo->id])
            ->assertCreated()
            ->assertJsonPath('pallet.batch_id', $batch->id);
    }

    public function test_create_pallet_rejects_batch_from_another_work_order(): void
    {
        $wo = WorkOrder::factory()->create();
        $otherBatch = Batch::factory()->create(['work_order_id' => WorkOrder::factory()->create()->id]);

        $this->actingAs($this->admin)
            ->postJson(route('packaging.pallets.create'), [
                'work_order_id' => $wo->id,
                'batch_id' => $otherBatch->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('pallets', ['work_order_id' => $wo->id]);
    }

    public function test_sync_shapes_expose_the_new_columns(): void
    {
        $registry = app(\App\Sync\ShapeRegistry::class);

        // pallets is a synced table; batch_id must be in the allowlist or live
        // views never see the pallet→batch link.
        $this->assertContains('batch_id', $registry->find('pallets')->columns());

        // work_orders shapes must carry customer_order_no for live filtering.
        $this->assertContains('customer_order_no', $registry->find('work_orders_all')->columns());
        $this->assertContains('customer_order_no', $registry->find('work_orders_active')->columns());
    }
}
