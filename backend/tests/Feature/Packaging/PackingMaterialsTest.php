<?php

namespace Tests\Feature\Packaging;

use App\Models\BatchStep;
use App\Models\BomItem;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\SerialUnit;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Packaging comes from the BOM: a carton per carton, a pallet per pallet on
 * the packing step; the station reserves it (with the lot, when lots are
 * tracked) before the first unit goes in, and books it as boxes close.
 */
class PackingMaterialsTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $admin;

    private ProductType $product;

    private ProcessTemplate $template;

    private TemplateStep $packStep;

    private Material $carton;

    private Material $palletMaterial;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        Role::findOrCreate('Admin', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->product = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $this->product->id]);
        TemplateStep::create(['process_template_id' => $this->template->id, 'step_number' => 1, 'name' => 'Assemble']);
        $this->packStep = TemplateStep::create([
            'process_template_id' => $this->template->id, 'step_number' => 2, 'name' => 'Pack',
            'kind' => TemplateStep::KIND_PACKING, 'config' => ['unit' => 'carton', 'carton_capacity' => 2, 'pallet_capacity' => 1],
        ]);
        $this->carton = Material::factory()->create(['code' => 'CTN-6', 'name' => 'Carton', 'tracking_type' => 'batch', 'stock_quantity' => 100, 'unit_of_measure' => 'pcs']);
        $this->palletMaterial = Material::factory()->create(['code' => 'PALLET-STD', 'name' => 'Standard pallet', 'tracking_type' => 'batch', 'stock_quantity' => 20, 'unit_of_measure' => 'pcs']);
        BomItem::create(['process_template_id' => $this->template->id, 'template_step_id' => $this->packStep->id, 'material_id' => $this->carton->id, 'quantity_per_unit' => 1, 'per' => BomItem::PER_CARTON, 'consumed_at' => 'during', 'scrap_percentage' => 0]);
        BomItem::create(['process_template_id' => $this->template->id, 'template_step_id' => $this->packStep->id, 'material_id' => $this->palletMaterial->id, 'quantity_per_unit' => 1, 'per' => BomItem::PER_PALLET, 'consumed_at' => 'during', 'scrap_percentage' => 0]);
    }

    /** An order with its assembly done, so the packing step is reachable, and units to pack. */
    private function order(int $qty = 4): array
    {
        $wo = WorkOrder::factory()->create(['product_type_id' => $this->product->id, 'process_snapshot' => $this->template->fresh()->toSnapshot(), 'planned_qty' => $qty, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $batch = app(WorkOrderService::class)->createBatch($wo, $qty);
        $svc = app(BatchService::class);
        $s1 = $batch->steps()->where('step_number', 1)->first();
        $svc->startStep($s1, $this->operator);
        $svc->recordQuantity($s1->fresh(), $this->operator, $qty, 0);
        $svc->completeStep($s1->fresh(), $this->operator);
        foreach (range(1, $qty) as $i) {
            SerialUnit::create(['serial_no' => "SN-{$wo->id}-{$i}", 'psn' => "P-{$wo->id}-{$i}", 'work_order_id' => $wo->id]);
        }

        return [$wo, $batch->steps()->where('step_number', 2)->firstOrFail()];
    }

    private function lot(Material $material, string $number, float $qty): MaterialLot
    {
        return MaterialLot::create(['material_id' => $material->id, 'lot_number' => $number, 'quantity_received' => $qty, 'quantity_available' => $qty, 'unit_of_measure' => 'pcs', 'received_at' => now(), 'status' => MaterialLot::STATUS_RELEASED]);
    }

    public function test_the_snapshot_folds_the_packing_capacities_into_a_per_unit_quantity(): void
    {
        $bom = collect($this->template->fresh()->toSnapshot()['bom'])->keyBy('material_code');

        // One carton per carton of 2 → half a carton per unit; one pallet per pallet of 1 carton → half a pallet per unit.
        $this->assertSame(0.5, $bom['CTN-6']['quantity_per_unit']);
        $this->assertSame('carton', $bom['CTN-6']['per']);
        $this->assertSame(1.0, $bom['CTN-6']['quantity_per_basis']);
        $this->assertSame(0.5, $bom['PALLET-STD']['quantity_per_unit']);
        $this->assertSame(2.0, BomItem::where('material_id', $this->carton->id)->first()->calculateRequiredQuantity(4));

        // The admin form takes the basis and shows it back.
        $foil = Material::factory()->create(['code' => 'FOIL', 'tracking_type' => 'batch']);
        $this->actingAs($this->admin)->post("/admin/product-types/{$this->product->id}/process-templates/{$this->template->id}/bom", [
            'material_id' => $foil->id, 'template_step_id' => $this->packStep->id, 'quantity_per_unit' => 2, 'per' => 'pallet', 'consumed_at' => 'during',
        ])->assertSessionHasNoErrors();
        $this->assertSame('pallet', BomItem::where('material_id', $foil->id)->value('per'));
        $this->actingAs($this->admin)->post("/admin/product-types/{$this->product->id}/process-templates/{$this->template->id}/bom", [
            'material_id' => Material::factory()->create()->id, 'quantity_per_unit' => 1, 'per' => 'box',
        ])->assertSessionHasErrors('per');
    }

    public function test_with_lot_tracking_the_station_makes_the_operator_pick_the_packaging_lots_first(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'lot_tracking_enabled'], ['value' => json_encode(true)]);
        $cartonLot = $this->lot($this->carton, 'CTN-LOT-A', 50);
        $palletLot = $this->lot($this->palletMaterial, 'PAL-LOT-1', 10);
        [$wo, $pack] = $this->order();

        // A guest first: actingAs() sticks for the rest of the test.
        $this->getJson(route('packaging.packing-steps.materials', $pack))->assertUnauthorized();
        $this->postJson(route('packaging.packing-steps.start', $pack), [])->assertUnauthorized();

        // The station lists the packaging with what the batch needs, waiting to be picked.
        $steps = $this->actingAs($this->operator)->getJson(route('packaging.packing-steps'))->assertOk()->json('steps');
        $materials = collect($steps)->firstWhere('id', $pack->id)['materials'];
        $this->assertSame(['CTN-6', 'PALLET-STD'], array_column($materials, 'code'));
        $this->assertSame(2.0, (float) $materials[0]['required_qty']);
        $this->assertTrue($materials[0]['needs_pick']);

        // A scan before the picks is refused and says why.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1"])
            ->assertStatus(422)->assertJsonPath('needs_picks', true)->assertJsonPath('packing_step_id', $pack->id);
        $this->assertNull(SerialUnit::where('psn', "P-{$wo->id}-1")->value('packed_at'));

        // The lots to pick from, then the start with the picks.
        $candidates = $this->actingAs($this->operator)->getJson(route('packaging.packing-steps.materials', $pack))->assertOk()->json('candidates');
        $this->assertSame('CTN-LOT-A', $candidates[0]['candidates'][0]['lot_number']);
        $this->actingAs($this->operator)->postJson(route('packaging.packing-steps.start', $pack), ['picks' => [
            $this->carton->id => [['material_lot_id' => $cartonLot->id, 'picked_qty' => 2]],
            $this->palletMaterial->id => [['material_lot_id' => $palletLot->id, 'picked_qty' => 2]],
        ]])->assertOk()->assertJsonPath('materials.0.status', 'allocated')->assertJsonPath('materials.0.lots.0.lot_number', 'CTN-LOT-A');
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $pack->fresh()->status);
        $this->assertSame(48.0, (float) $cartonLot->fresh()->quantity_available);

        // Packing goes on; closing the carton uses one carton, the full pallet uses one pallet.
        $pallet = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);
        $carton = $this->actingAs($this->operator)->postJson('/packaging/cartons', ['work_order_id' => $wo->id, 'pallet_id' => $pallet->id])->assertCreated()->json('carton');
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'carton_id' => $carton['id']])->assertOk();
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-2", 'carton_id' => $carton['id']])->assertOk();
        $this->actingAs($this->operator)->postJson("/packaging/cartons/{$carton['id']}/close")->assertOk()->assertJsonPath('pallet_closed.pallet_no', $pallet->pallet_no);

        $cartonAllocation = MaterialAllocation::where('batch_id', $pack->batch_id)->where('material_id', $this->carton->id)->firstOrFail();
        $palletAllocation = MaterialAllocation::where('batch_id', $pack->batch_id)->where('material_id', $this->palletMaterial->id)->firstOrFail();
        $this->assertSame(1.0, (float) $cartonAllocation->consumed_qty);
        $this->assertSame(1.0, (float) $palletAllocation->consumed_qty);

    }

    public function test_without_lot_tracking_the_first_scan_reserves_the_packaging_itself(): void
    {
        [$wo, $pack] = $this->order();

        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1"])->assertOk();
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $pack->fresh()->status);
        $allocation = MaterialAllocation::where('batch_id', $pack->batch_id)->where('material_id', $this->carton->id)->firstOrFail();
        $this->assertSame(2.0, (float) $allocation->allocated_qty);

        $materials = collect($this->actingAs($this->operator)->getJson(route('packaging.packing-steps'))->json('steps'))->firstWhere('id', $pack->id)['materials'];
        $this->assertSame('allocated', $materials[0]['status']);
        $this->assertFalse($materials[0]['needs_pick']);
    }
}
