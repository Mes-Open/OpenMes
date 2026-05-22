<?php

namespace Tests\Unit\Services;

use App\Models\AllocationLotPick;
use App\Models\Batch;
use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\LotPickingService;
use App\Services\Quality\InboundInspectionService;
use Database\Seeders\IssueTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tenant isolation regression tests.
 *
 * Phase 0 introduced tenant_id on material_allocations and material_lots;
 * the lot picking machinery had to be retrofitted so that derived rows
 * (allocation_lot_picks, MaterialLot rows from inspections) inherit the
 * tenant of their parent rather than relying on the implicit auth-based
 * default in HasTenant — which is missing in queue workers / super-admin
 * contexts.
 */
class LotPickingTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IssueTypesSeeder::class);
    }

    public function test_pick_inherits_tenant_id_from_allocation(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant 1']);

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $material = Material::create([
            'code' => 'M', 'name' => 'M',
            'material_type_id' => $type->id,
            'unit_of_measure' => 'kg',
            'stock_quantity' => 1000,
        ]);

        $lot = MaterialLot::create([
            'tenant_id' => $tenant->id,
            'material_id' => $material->id,
            'lot_number' => 'LOT-T1',
            'received_qty' => 100,
            'available_qty' => 100,
            'received_at' => now(),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);

        $productType = ProductType::factory()->create();
        $wo = WorkOrder::factory()->create(['product_type_id' => $productType->id]);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);

        $allocation = MaterialAllocation::create([
            'tenant_id' => $tenant->id,
            'batch_id' => $batch->id,
            'material_id' => $material->id,
            'work_order_id' => $wo->id,
            'allocated_qty' => 100,
            'expected_qty' => 100,
            'status' => MaterialAllocation::STATUS_ALLOCATED,
            'allocated_by' => User::factory()->create()->id,
            'allocated_at' => now(),
        ]);

        $picks = app(LotPickingService::class)
            ->pickForAllocation($allocation, $material, 50, 'fefo');

        $this->assertCount(1, $picks);
        $this->assertSame($tenant->id, (int) $picks[0]->tenant_id);
        $this->assertSame($lot->id, (int) $picks[0]->material_lot_id);

        // Round-trip through the DB to verify the column was actually persisted
        // (and not just set on the in-memory model).
        $persisted = AllocationLotPick::find($picks[0]->id);
        $this->assertSame($tenant->id, (int) $persisted->tenant_id);
    }

    public function test_inspection_lot_inherits_tenant_id_from_inspection(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant 2']);

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $material = Material::create([
            'code' => 'M2', 'name' => 'M2',
            'material_type_id' => $type->id,
            'unit_of_measure' => 'kg',
            'stock_quantity' => 0,
        ]);

        $inspector = User::factory()->create();

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(true)],
        );

        $plan = InspectionPlan::create([
            'name' => 'P',
            'material_id' => $material->id,
            'criteria' => [['name' => 'V', 'type' => 'pass_fail', 'required' => true]],
            'is_active' => true,
        ]);

        // Create inspection directly with tenant_id=2; simulates a queue-worker
        // / super-admin context where auth() does not have a tenant.
        $inspection = Inspection::factory()->create([
            'tenant_id' => $tenant->id,
            'inspection_plan_id' => $plan->id,
            'material_id' => $material->id,
            'lot_number' => 'LOT-INSP-T2',
            'quantity_received' => 75,
            'inspector_id' => $inspector->id,
            'started_at' => now(),
            'status' => Inspection::STATUS_PENDING,
        ]);

        // Snapshot the plan's criteria as a result row (mirrors what start()
        // would have done) so complete() has something to evaluate.
        $inspection->results()->create([
            'criterion_name' => 'V',
            'criterion_type' => 'pass_fail',
            'required' => true,
        ]);

        $svc = app(InboundInspectionService::class);
        $svc->recordResult($inspection->results->first(), ['value_boolean' => true]);
        $svc->complete($inspection->fresh('results'));

        $lot = MaterialLot::firstWhere('inspection_id', $inspection->id);
        $this->assertNotNull($lot, 'Expected a MaterialLot to be created on complete().');
        $this->assertSame($tenant->id, (int) $lot->tenant_id);
        $this->assertSame(MaterialLot::STATUS_AVAILABLE, $lot->status);
    }
}
