<?php

namespace Tests\Feature\Web\Operator;

use App\Models\AllocationLotPick;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * WO-time lot picking (ERP-aligned "suggest + override"): operators pick/override
 * which lots are consumed when starting a batch step. Lots only (phase 1).
 */
class WoTimeLotPickingTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Material $material;

    private Batch $batch;

    private BatchStep $step;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Operator', 'guard_name' => 'web']);
        Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
        $this->operator = User::factory()->create(['account_type' => 'operator']);
        $this->operator->assignRole('Operator');

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'WIDGET',
            'name' => 'Widget',
            'material_type_id' => $type->id,
            'unit_of_measure' => 'pcs',
            'tracking_type' => 'batch',
            'stock_quantity' => 1000,
        ]);

        $wo = WorkOrder::factory()->create([
            'product_type_id' => ProductType::factory()->create()->id,
            'process_snapshot' => [
                'steps' => [
                    ['step_number' => 1, 'name' => 'Assemble', 'is_optional' => false, 'variant_group' => null, 'is_default_variant' => false],
                ],
                'bom' => [[
                    'material_id' => $this->material->id,
                    'material_code' => $this->material->code,
                    'material_name' => $this->material->name,
                    'tracking_type' => 'batch',
                    'unit_of_measure' => 'pcs',
                    'quantity_per_unit' => 2.0,
                    'scrap_percentage' => 0.0,
                    'consumed_at' => 'start',
                    'step_number' => null,
                ]],
            ],
        ]);

        // target 10 * 2 per unit = 20 required.
        $this->batch = app(WorkOrderService::class)->createBatch($wo, 10);
        $this->step = $this->batch->steps()->where('step_number', 1)->first();
    }

    private function enableLotTracking(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'lot_tracking_enabled'], ['value' => json_encode(true)]);
    }

    private function makeLot(string $number, float $qty): MaterialLot
    {
        return MaterialLot::create([
            'material_id' => $this->material->id,
            'lot_number' => $number,
            'unit_of_measure' => 'pcs',
            'quantity_received' => $qty,
            'quantity_available' => $qty,
            'received_at' => now(),
            'status' => MaterialLot::STATUS_RELEASED,
        ]);
    }

    private function actingOperator()
    {
        return $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->batch->workOrder->line_id]);
    }

    public function test_preview_returns_proposal_when_tracking_on(): void
    {
        $this->enableLotTracking();
        $this->makeLot('LOT-A', 50);

        $this->actingOperator()
            ->getJson("/operator/batch-step/{$this->step->id}/pick-preview")
            ->assertOk()
            ->assertJsonPath('materials.0.material_id', $this->material->id)
            ->assertJsonPath('materials.0.required_qty', 20)
            ->assertJsonCount(1, 'materials.0.candidates');
    }

    public function test_preview_empty_when_tracking_off(): void
    {
        $this->makeLot('LOT-A', 50);

        $this->actingOperator()
            ->getJson("/operator/batch-step/{$this->step->id}/pick-preview")
            ->assertOk()
            ->assertExactJson(['materials' => []]);
    }

    public function test_operator_can_start_step_with_split_manual_picks(): void
    {
        $this->enableLotTracking();
        $lotA = $this->makeLot('LOT-A', 12);
        $lotB = $this->makeLot('LOT-B', 50);

        $this->actingOperator()
            ->post("/operator/batch-step/{$this->step->id}/start", [
                'picks' => [[
                    'material_id' => $this->material->id,
                    'lots' => [
                        ['material_lot_id' => $lotA->id, 'picked_qty' => 12],
                        ['material_lot_id' => $lotB->id, 'picked_qty' => 8],
                    ],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->step->fresh()->status);
        $this->assertSame(2, AllocationLotPick::where('picking_strategy', 'manual')->count());
        $this->assertEqualsWithDelta(0.0, (float) $lotA->fresh()->quantity_available, 0.0001);
        $this->assertEqualsWithDelta(42.0, (float) $lotB->fresh()->quantity_available, 0.0001);
    }

    public function test_picks_not_summing_to_required_are_rejected_and_step_stays_pending(): void
    {
        $this->enableLotTracking();
        $lotA = $this->makeLot('LOT-A', 50);

        $this->actingOperator()
            ->post("/operator/batch-step/{$this->step->id}/start", [
                'picks' => [[
                    'material_id' => $this->material->id,
                    'lots' => [['material_lot_id' => $lotA->id, 'picked_qty' => 15]], // need 20
                ]],
            ])
            ->assertSessionHasErrors('picks');

        $this->assertNotSame(BatchStep::STATUS_IN_PROGRESS, $this->step->fresh()->status);
        $this->assertSame(0, AllocationLotPick::count());
        $this->assertEqualsWithDelta(50.0, (float) $lotA->fresh()->quantity_available, 0.0001);
    }

    public function test_pick_exceeding_lot_available_is_rejected(): void
    {
        $this->enableLotTracking();
        $lotA = $this->makeLot('LOT-A', 15); // less than the 20 required

        $this->actingOperator()
            ->post("/operator/batch-step/{$this->step->id}/start", [
                'picks' => [[
                    'material_id' => $this->material->id,
                    'lots' => [['material_lot_id' => $lotA->id, 'picked_qty' => 20]],
                ]],
            ])
            ->assertSessionHasErrors('picks');

        $this->assertSame(0, AllocationLotPick::count());
        $this->assertEqualsWithDelta(15.0, (float) $lotA->fresh()->quantity_available, 0.0001);
    }

    public function test_start_without_picks_falls_back_to_auto_picking(): void
    {
        $this->enableLotTracking();
        $this->makeLot('LOT-A', 50);

        $this->actingOperator()
            ->post("/operator/batch-step/{$this->step->id}/start")
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AllocationLotPick::where('picking_strategy', 'fefo')->count());
    }

    public function test_guest_cannot_preview_or_start(): void
    {
        $this->enableLotTracking();
        $this->makeLot('LOT-A', 50);

        $this->get("/operator/batch-step/{$this->step->id}/pick-preview")->assertRedirect();
        $this->post("/operator/batch-step/{$this->step->id}/start")->assertRedirect();
        $this->assertSame(0, AllocationLotPick::count());
    }

    public function test_wrong_role_is_forbidden(): void
    {
        $this->enableLotTracking();
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)
            ->withSession(['selected_line_id' => $this->batch->workOrder->line_id])
            ->get("/operator/batch-step/{$this->step->id}/pick-preview")
            ->assertForbidden();
    }

    public function test_step_on_another_line_is_rejected(): void
    {
        $this->enableLotTracking();

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->batch->workOrder->line_id + 999])
            ->post("/operator/batch-step/{$this->step->id}/start")
            ->assertSessionHas('error');

        $this->assertNotSame(BatchStep::STATUS_IN_PROGRESS, $this->step->fresh()->status);
    }

    public function test_completing_batch_writes_genealogy_from_manual_picks(): void
    {
        $this->enableLotTracking();
        $lotA = $this->makeLot('LOT-A', 50);

        $this->actingOperator()
            ->post("/operator/batch-step/{$this->step->id}/start", [
                'picks' => [[
                    'material_id' => $this->material->id,
                    'lots' => [['material_lot_id' => $lotA->id, 'picked_qty' => 20]],
                ]],
            ])
            ->assertSessionHasNoErrors();

        // Single-step batch → completing the step completes & consumes the batch.
        $this->actingOperator()
            ->post("/operator/batch-step/{$this->step->id}/complete")
            ->assertSessionHasNoErrors();

        $rows = BatchStepLotConsumption::where('material_lot_id', $lotA->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->step->id, $rows->first()->batch_step_id);
        $this->assertEqualsWithDelta(20.0, (float) $rows->first()->quantity_consumed, 0.0001);
    }
}
