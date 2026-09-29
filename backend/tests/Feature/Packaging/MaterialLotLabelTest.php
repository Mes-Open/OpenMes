<?php

namespace Tests\Feature\Packaging;

use App\Models\LabelTemplate;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\User;
use App\Services\Packaging\LabelGenerator;
use Database\Seeders\LabelTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The IQC label of a received material lot: lot, material and the inspection verdict. */
class MaterialLotLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private MaterialLot $lot;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $material = Material::factory()->create(['code' => 'MAT-IQC', 'name' => 'Checked part', 'unit_of_measure' => 'pcs']);
        $this->lot = MaterialLot::create(['lot_number' => 'LOT-IQC-1', 'material_id' => $material->id, 'quantity_received' => 500, 'quantity_available' => 500,
            'unit_of_measure' => 'pcs', 'received_at' => now()->subDay(), 'status' => MaterialLot::STATUS_RELEASED, 'supplier_lot_no' => 'SUP-9']);
    }

    public function test_the_label_prints_with_the_built_in_layout_and_with_the_seeded_default(): void
    {
        $this->get(route('packaging.labels.material-lot.pdf', $this->lot))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('packaging.labels.material-lot.pdf', $this->lot))->assertForbidden();

        // No template configured yet: the built-in layout prints.
        $this->actingAs($this->operator)->get(route('packaging.labels.material-lot.pdf', $this->lot))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->seed(LabelTemplatesSeeder::class);
        $template = LabelTemplate::defaultFor(LabelTemplate::TYPE_MATERIAL_LOT);
        $this->assertNotNull($template, 'the defaults include an IQC label');

        $zpl = $this->actingAs($this->operator)->get(route('packaging.labels.material-lot.zpl', $this->lot))->assertOk()->getContent();
        $this->assertStringContainsString('LOT-IQC-1', $zpl);
        $this->assertStringContainsString('MAT-IQC', $zpl);
        $this->assertStringContainsString('SUP-9', $zpl);
    }

    public function test_the_status_line_carries_the_inspection_verdict(): void
    {
        $template = new LabelTemplate(['type' => LabelTemplate::TYPE_MATERIAL_LOT, 'size' => '100x50', 'barcode_format' => 'code128',
            'fields_config' => LabelTemplate::defaultFieldsFor(LabelTemplate::TYPE_MATERIAL_LOT)]);

        $released = app(LabelGenerator::class)->zplForMaterialLots(collect([$this->lot]), $template);
        $this->lot->update(['status' => MaterialLot::STATUS_QUARANTINE]);
        $held = app(LabelGenerator::class)->zplForMaterialLots(collect([$this->lot->fresh()]), $template);

        $this->assertStringContainsString(__('IQC: released'), $released);
        $this->assertStringContainsString(__('IQC: quarantine'), $held);
        $this->assertContains('material', LabelTemplate::fieldsForType(LabelTemplate::TYPE_MATERIAL_LOT));
    }
}
