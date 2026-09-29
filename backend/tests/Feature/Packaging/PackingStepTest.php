<?php

namespace Tests\Feature\Packaging;

use App\Models\BatchStep;
use App\Models\LabelTemplate;
use App\Models\Pallet;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\SerialUnit;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Packing as a routing step: configured on the template, frozen into the order, counted at the station. */
class PackingStepTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    private function templateWithPacking(): ProcessTemplate
    {
        $product = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id]);
        TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Assemble']);
        TemplateStep::create([
            'process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Pack',
            'kind' => TemplateStep::KIND_PACKING, 'config' => ['unit' => 'carton', 'carton_capacity' => 2, 'pallet_capacity' => 10],
        ]);

        return $template->fresh();
    }

    public function test_a_packing_step_is_validated_and_stored_with_its_config(): void
    {
        $product = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id]);
        $url = "/admin/product-types/{$product->id}/process-templates/{$template->id}/steps";

        $this->actingAs($this->admin)->from('/x')->post($url, ['name' => 'Pack', 'kind' => 'shipping'])->assertSessionHasErrors(['kind']);
        $this->actingAs($this->admin)->from('/x')->post($url, ['name' => 'Pack', 'kind' => 'packing', 'config' => ['unit' => 'box']])->assertSessionHasErrors(['config.unit']);

        $this->actingAs($this->admin)->post($url, ['name' => 'Pack', 'kind' => 'packing', 'config' => ['unit' => 'carton', 'carton_capacity' => 6, 'pallet_capacity' => '']])
            ->assertSessionHasNoErrors();
        $step = TemplateStep::where('name', 'Pack')->firstOrFail();
        $this->assertSame('packing', $step->kind);
        $this->assertSame(['unit' => 'carton', 'carton_capacity' => 6], $step->config);

        // A production step drops any config that was sent along.
        $this->actingAs($this->admin)->post($url, ['name' => 'Weld', 'config' => ['carton_capacity' => 3]])->assertSessionHasNoErrors();
        $this->assertNull(TemplateStep::where('name', 'Weld')->firstOrFail()->config);
        $this->assertSame('production', TemplateStep::where('name', 'Weld')->firstOrFail()->kind);
    }

    public function test_the_snapshot_and_the_batch_steps_carry_the_kind_and_config(): void
    {
        $template = $this->templateWithPacking();
        $snapshot = $template->toSnapshot();
        $this->assertSame('packing', $snapshot['steps'][1]['kind']);
        $this->assertSame(2, $snapshot['steps'][1]['config']['carton_capacity']);

        $wo = WorkOrder::factory()->create(['product_type_id' => $template->product_type_id, 'process_snapshot' => $snapshot, 'planned_qty' => 4, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $batch = app(WorkOrderService::class)->createBatch($wo, 4);

        $pack = $batch->steps()->where('step_number', 2)->firstOrFail();
        $this->assertTrue($pack->isPacking());
        $this->assertSame(['unit' => 'carton', 'carton_capacity' => 2, 'pallet_capacity' => 10], $pack->config);
    }

    public function test_packing_a_unit_counts_on_the_orders_packing_step_once_it_is_reachable(): void
    {
        $template = $this->templateWithPacking();
        $wo = WorkOrder::factory()->create(['product_type_id' => $template->product_type_id, 'process_snapshot' => $template->toSnapshot(), 'planned_qty' => 4, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $batch = app(WorkOrderService::class)->createBatch($wo, 4);
        $assemble = $batch->steps()->where('step_number', 1)->firstOrFail();
        $pack = $batch->steps()->where('step_number', 2)->firstOrFail();
        SerialUnit::create(['serial_no' => 'SN-1', 'psn' => 'P-1', 'work_order_id' => $wo->id]);
        SerialUnit::create(['serial_no' => 'SN-2', 'psn' => 'P-2', 'work_order_id' => $wo->id]);

        // Assembly not done: the pack step is not reachable, packing still works, nothing is counted.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => 'P-1'])->assertOk();
        $this->assertSame(0.0, (float) $pack->fresh()->passed_qty);

        // Assembly done -> pack step READY: the next packed unit starts it and counts.
        $svc = app(\App\Services\WorkOrder\BatchService::class);
        $svc->startStep($assemble, $this->operator);
        $svc->completeStep($assemble, $this->operator);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => 'P-2'])->assertOk();

        $pack = $pack->fresh();
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $pack->status);
        $this->assertSame(1.0, (float) $pack->passed_qty);
        $this->assertSame($this->operator->id, $pack->started_by_id);
    }

    /** An order whose packing step is reachable, with units to pack. */
    private function packableOrder(array $config): WorkOrder
    {
        $product = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id]);
        TemplateStep::create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Pack', 'kind' => TemplateStep::KIND_PACKING, 'config' => $config]);
        $wo = WorkOrder::factory()->create(['product_type_id' => $product->id, 'process_snapshot' => $template->fresh()->toSnapshot(), 'planned_qty' => 10, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        app(WorkOrderService::class)->createBatch($wo, 10);
        foreach (range(1, 4) as $i) {
            SerialUnit::create(['serial_no' => "SN-{$wo->id}-{$i}", 'psn' => "P-{$wo->id}-{$i}", 'work_order_id' => $wo->id]);
        }

        return $wo;
    }

    public function test_on_auto_a_unit_of_another_order_gets_its_own_carton_and_the_first_order_gets_its_box_back(): void
    {
        $first = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5]);
        $second = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5]);
        $scan = fn (string $psn, ?int $cartonId) => $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => $psn, 'carton_id' => $cartonId, 'auto_carton' => true])->assertOk()->json();

        $boxA = $scan("P-{$first->id}-1", null)['unit']['carton']['id'];

        // The bench still shows the first order's box: the second order's unit is not refused, it gets its own.
        $b = $scan("P-{$second->id}-1", $boxA);
        $this->assertNotNull($b['carton_opened']);
        $boxB = $b['unit']['carton']['id'];
        $this->assertNotSame($boxA, $boxB);

        // Back to the first order: its open box is taken again, no new one.
        $a2 = $scan("P-{$first->id}-2", $boxB);
        $this->assertNull($a2['carton_opened']);
        $this->assertSame($boxA, $a2['unit']['carton']['id']);
        $this->assertSame($this->operator->id, \App\Models\UnitCarton::find($boxA)->active_by_id);

        // A box picked by hand is still checked.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$second->id}-2", 'carton_id' => $boxA])->assertUnprocessable();
    }

    public function test_on_auto_a_packed_unit_rescanned_at_another_bench_stays_in_its_carton(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5]);
        $box = $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'auto_carton' => true])
            ->assertOk()->json('unit.carton.id');

        // Another packer's bench has no box of its own: the rescan neither opens an
        // empty carton nor refuses the unit - it says where the unit already is.
        $other = User::factory()->create();
        $other->assignRole('Operator');
        $cartons = \App\Models\UnitCarton::count();
        $this->actingAs($other)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'auto_carton' => true])
            ->assertOk()
            ->assertJsonPath('already_packed', true)
            ->assertJsonPath('unit.carton.id', $box)
            ->assertJsonPath('unit.carton.qty', 1)
            ->assertJsonPath('carton_opened', null);
        $this->assertSame($cartons, \App\Models\UnitCarton::count());
    }

    public function test_a_carton_holds_the_packing_steps_size_and_the_server_enforces_it(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 2]);
        $scan = fn (string $psn, array $body = []) => $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => $psn] + $body);

        // No pallet on the bench: the box opened by the scan still says when it is full.
        $first = $scan("P-{$wo->id}-1", ['auto_carton' => true])->assertOk()->assertJsonPath('carton_full', false)->assertJsonPath('unit.work_order_id', $wo->id);
        $box = $first->json('unit.carton.id');
        $scan("P-{$wo->id}-2", ['auto_carton' => true])->assertOk()->assertJsonPath('unit.carton.id', $box)->assertJsonPath('carton_full', true);

        // The station missed the close: a box named by hand is refused, "auto" starts the next one.
        $scan("P-{$wo->id}-3", ['carton_id' => $box])->assertStatus(422)->assertJsonPath('message', 'Carton '.\App\Models\UnitCarton::find($box)->carton_no.' is full (2 units).');
        $next = $scan("P-{$wo->id}-3", ['carton_id' => $box, 'auto_carton' => true])->assertOk()->json('unit.carton.id');
        $this->assertNotSame($box, $next);
        $this->assertSame(2, \App\Models\UnitCarton::find($box)->qty);
    }

    public function test_a_box_and_the_bench_pallet_stay_together(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5]);
        $palletA = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);
        $palletB = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);
        $box = $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'auto_carton' => true])->assertOk()->json('unit.carton.id');

        // A box with no pallet yet goes onto the bench's pallet with the unit, both counted.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-2", 'carton_id' => $box, 'pallet_id' => $palletA->id])->assertOk();
        $this->assertSame($palletA->id, \App\Models\UnitCarton::find($box)->pallet_id);
        $this->assertSame(2, $palletA->fresh()->qty);
        $this->assertSame($palletA->id, SerialUnit::where('psn', "P-{$wo->id}-1")->value('pallet_id'));

        // Now on pallet A, the box does not also feed pallet B.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-3", 'carton_id' => $box, 'pallet_id' => $palletB->id])->assertStatus(422);
        $this->assertSame(0, $palletB->fresh()->qty);
    }

    public function test_auto_does_not_take_over_a_box_another_packer_is_filling(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5]);
        $box = $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'auto_carton' => true])->assertOk()->json('unit.carton.id');
        $other = User::factory()->create();
        $other->assignRole('Operator');
        \App\Models\UnitCarton::whereKey($box)->update(['active_by_id' => $other->id]);

        $mine = $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-2", 'auto_carton' => true])->assertOk()->json('unit.carton.id');
        $this->assertNotSame($box, $mine);
        $this->assertSame($other->id, \App\Models\UnitCarton::find($box)->active_by_id);
    }

    public function test_a_refused_scan_leaves_no_empty_carton_and_closing_twice_books_once(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5]);
        $closed = Pallet::create(['work_order_id' => $wo->id, 'status' => 'closed', 'qty' => 0]);
        $cartons = \App\Models\UnitCarton::count();
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'pallet_id' => $closed->id, 'auto_carton' => true])->assertStatus(422);
        $this->assertSame($cartons, \App\Models\UnitCarton::count());
        $this->actingAs($this->operator)->postJson(route('packaging.cartons.store'), ['work_order_id' => $wo->id, 'pallet_id' => $closed->id])->assertStatus(422);

        $open = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);
        $this->actingAs($this->operator)->postJson(route('packaging.pallets.close', $open))->assertOk();
        $this->actingAs($this->operator)->postJson(route('packaging.pallets.close', $open))->assertStatus(422);
    }

    public function test_the_first_scan_opens_a_carton_and_the_step_decides_whether_each_unit_prints_a_label(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5, 'pallet_capacity' => 10]);

        // No box open and the bench on "auto": the scan opens one.
        $scan = $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'auto_carton' => true])->assertOk()->json();
        $this->assertNotNull($scan['carton_opened']);
        $this->assertSame($scan['carton_opened'], $scan['unit']['carton']['carton_no']);
        $this->assertTrue($scan['label_needed']);

        // The next scan reuses the operator's open carton.
        $scan2 = $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-2", 'auto_carton' => true])->assertOk()->json();
        $this->assertNull($scan2['carton_opened']);
        $this->assertSame($scan['unit']['carton']['id'], $scan2['unit']['carton']['id']);

        // By default every scan prints the unit's label (a box label), even for a unit labelled upstream.
        SerialUnit::where('psn', "P-{$wo->id}-3")->first()->update(['extra_data' => ['label_applied_at' => now()->toIso8601String()]]);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-3", 'auto_carton' => true])
            ->assertOk()->assertJsonPath('label_needed', true);

        // A step set not to print one leaves a labelled unit alone and still labels an unlabelled one.
        $quiet = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5, 'unit_label' => false]);
        $this->assertFalse(TemplateStep::normalisePackingConfig(['unit_label' => false])['unit_label']);
        $this->assertArrayNotHasKey('unit_label', TemplateStep::normalisePackingConfig(['unit_label' => null]));
        SerialUnit::where('psn', "P-{$quiet->id}-1")->first()->update(['extra_data' => ['label_applied_at' => now()->toIso8601String()]]);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$quiet->id}-1", 'auto_carton' => true])
            ->assertOk()->assertJsonPath('label_needed', false);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$quiet->id}-2", 'auto_carton' => true])
            ->assertOk()->assertJsonPath('label_needed', true);

        // An explicit destination (no flag) opens nothing.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-4"])
            ->assertOk()->assertJsonPath('carton_opened', null)->assertJsonPath('unit.carton', null);

        // An order that packs loose onto pallets never gets a carton.
        $loose = $this->packableOrder(['unit' => 'pallet', 'pallet_capacity' => 10]);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$loose->id}-1", 'auto_carton' => true])
            ->assertOk()->assertJsonPath('carton_opened', null)->assertJsonPath('unit.carton', null);
    }

    public function test_a_step_with_a_weight_check_weighs_each_unit_before_it_is_packed(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5, 'weight_expected_g' => 500, 'weight_tolerance_g' => 10]);
        $this->assertSame(500.0, TemplateStep::normalisePackingConfig(['weight_expected_g' => '500'])['weight_expected_g']);
        $this->assertSame(0.0, TemplateStep::normalisePackingConfig(['weight_expected_g' => '500'])['weight_tolerance_g']);
        $this->assertArrayNotHasKey('weight_tolerance_g', TemplateStep::normalisePackingConfig(['weight_tolerance_g' => 5]));

        // No weight: the station asks for it, nothing is packed.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1"])
            ->assertStatus(422)->assertJsonPath('needs_weight', true)->assertJsonPath('expected_g', 500);
        $this->assertNull(SerialUnit::where('psn', "P-{$wo->id}-1")->value('packed_at'));

        // Out of tolerance: refused, the reading kept on the unit's history.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'weight_g' => 520])
            ->assertStatus(422)->assertJsonPath('weight_out_of_tolerance', true);
        $unit = SerialUnit::where('psn', "P-{$wo->id}-1")->first();
        $this->assertNull($unit->packed_at);
        $check = $unit->history()->reorder('id', 'desc')->first();
        $this->assertSame('weight_check', $check->parameters['event']);
        $this->assertNull($check->result); // a scale reading is not a test verdict: the pallet's gate is not touched

        // Within tolerance: packed, the weight on the "packed" event.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'weight_g' => 492.5])->assertOk();
        $packed = $unit->history()->reorder('id', 'desc')->first();
        $this->assertSame('packed', $packed->parameters['event']);
        $this->assertEquals(492.5, $packed->parameters['weight_g']);

        // A held unit is refused before the scale is asked for.
        SerialUnit::where('psn', "P-{$wo->id}-3")->update(['status' => SerialUnit::STATUS_BLOCKED]);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-3"])
            ->assertStatus(422)->assertJsonMissingPath('needs_weight');

        // A negative reading is a bad value, not a weight.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-2", 'weight_g' => -1])
            ->assertStatus(422)->assertJsonValidationErrors('weight_g');

        // The step editor stores the check; a steps without it keeps packing as before.
        $plain = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5]);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$plain->id}-1"])->assertOk();
    }

    public function test_putting_a_carton_on_a_pallet_counts_each_unit_once_and_keeps_orders_apart(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5, 'pallet_capacity' => 10]);
        $pallet = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);
        $carton = $this->actingAs($this->operator)->postJson('/packaging/cartons', ['work_order_id' => $wo->id])->assertCreated()->json('carton');

        // Two units in the box; one of them was also scanned straight onto the pallet.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'carton_id' => $carton['id']])->assertOk();
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-2", 'carton_id' => $carton['id']])->assertOk();
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'pallet_id' => $pallet->id])->assertOk();
        $this->assertSame(1, (int) $pallet->fresh()->qty);

        // The box goes on: the other unit arrives, the first is not counted again.
        $this->actingAs($this->operator)->postJson(route('packaging.cartons.pallet', $carton['id']), ['pallet_id' => $pallet->id])->assertOk();
        $this->assertSame(2, (int) $pallet->fresh()->qty);
        // Unit 1 reached the pallet by its own scan ("packed" carries the pallet); only unit 2 is palletised by the box.
        $this->assertSame(0, SerialUnit::where('psn', "P-{$wo->id}-1")->first()->history()->where('parameters->event', 'palletised')->count());
        $this->assertSame(1, SerialUnit::where('psn', "P-{$wo->id}-2")->first()->history()->where('parameters->event', 'palletised')->count());

        // Another order's pallet refuses the box; a box whose unit sits on another pallet is refused too.
        $other = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5, 'pallet_capacity' => 10]);
        $othersPallet = Pallet::create(['work_order_id' => $other->id, 'status' => 'open', 'qty' => 0]);
        $carton2 = $this->actingAs($this->operator)->postJson('/packaging/cartons', ['work_order_id' => $wo->id])->assertCreated()->json('carton');
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-3", 'carton_id' => $carton2['id']])->assertOk();
        $this->actingAs($this->operator)->postJson(route('packaging.cartons.pallet', $carton2['id']), ['pallet_id' => $othersPallet->id])->assertStatus(422);
        $second = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-3", 'pallet_id' => $second->id])->assertOk();
        $this->actingAs($this->operator)->postJson(route('packaging.cartons.pallet', $carton2['id']), ['pallet_id' => $pallet->id])->assertStatus(422);
        $this->assertSame(2, (int) $pallet->fresh()->qty);
    }

    public function test_a_pallet_closes_itself_when_the_configured_number_of_cartons_is_reached(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 2, 'pallet_capacity' => 1]);
        $pallet = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);
        $carton = $this->actingAs($this->operator)->postJson('/packaging/cartons', ['work_order_id' => $wo->id, 'pallet_id' => $pallet->id])->assertCreated()->json('carton');

        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'carton_id' => $carton['id']])->assertOk()->assertJsonPath('pallet_closed', null);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-2", 'carton_id' => $carton['id']])->assertOk();
        $this->assertSame('open', $pallet->fresh()->status->value);

        // Closing the carton fills the pallet: it closes and the station gets its label to print.
        $this->actingAs($this->operator)->postJson("/packaging/cartons/{$carton['id']}/close")
            ->assertOk()
            ->assertJsonPath('pallet_closed.pallet_no', $pallet->pallet_no)
            ->assertJsonPath('pallet_closed.qty', 2);
        $this->assertSame('closed', $pallet->fresh()->status->value);
        $this->assertNull($pallet->fresh()->active_by_id);
    }

    public function test_a_pallet_packed_with_loose_units_closes_at_its_unit_capacity(): void
    {
        $wo = $this->packableOrder(['unit' => 'pallet', 'pallet_capacity' => 2]);
        $pallet = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);

        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'pallet_id' => $pallet->id])->assertOk()->assertJsonPath('pallet_closed', null);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-2", 'pallet_id' => $pallet->id])
            ->assertOk()
            ->assertJsonPath('pallet_closed.pallet_no', $pallet->pallet_no);
        $this->assertSame('closed', $pallet->fresh()->status->value);

        // A closed pallet takes no more: the next unit is refused.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-3", 'pallet_id' => $pallet->id])->assertStatus(422);
    }

    public function test_labels_print_on_the_template_the_packing_step_names(): void
    {
        $cartonTemplate = LabelTemplate::create(['name' => 'Export carton', 'type' => LabelTemplate::TYPE_CARTON, 'size' => '100x150', 'fields_config' => [], 'barcode_format' => 'qr', 'is_default' => false, 'is_active' => true]);
        $wo = $this->packableOrder(['unit' => 'carton', 'label_template_id' => $cartonTemplate->id]);
        $carton = $this->actingAs($this->operator)->postJson('/packaging/cartons', ['work_order_id' => $wo->id])->assertCreated()->json('carton');
        $this->assertStringContainsString("template={$cartonTemplate->id}", $carton['label_pdf']);

        // The step's template is for the carton, so the unit label stays on its type's default.
        $scan = $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'carton_id' => $carton['id']])->assertOk()->json();
        $this->assertStringNotContainsString('template=', $scan['label_pdf']);

        // A step that packs single units names the unit label instead.
        $unitTemplate = LabelTemplate::create(['name' => 'Big SN', 'type' => LabelTemplate::TYPE_SERIAL_UNIT, 'size' => '50x30', 'fields_config' => [], 'barcode_format' => 'qr', 'is_default' => false, 'is_active' => true]);
        $wo2 = $this->packableOrder(['unit' => 'unit', 'label_template_id' => $unitTemplate->id]);
        $scan = $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo2->id}-1"])->assertOk()->json();
        $this->assertStringContainsString("template={$unitTemplate->id}", $scan['label_pdf']);
        $this->assertStringContainsString("template={$unitTemplate->id}", $scan['label_zpl']);
    }

    public function test_a_rescanned_label_counts_once_and_a_unit_stays_on_its_pallet(): void
    {
        $wo = $this->packableOrder(['unit' => 'pallet', 'pallet_capacity' => 10]);
        $pack = BatchStep::where('kind', 'packing')->whereHas('batch', fn ($q) => $q->where('work_order_id', $wo->id))->firstOrFail();
        $first = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);
        $second = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 0]);

        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'pallet_id' => $first->id])
            ->assertOk()->assertJsonPath('already_packed', false);
        // The wedge scanner fired twice: still one unit on the step, one on the pallet,
        // and the bench is told the unit was already packed rather than packed again.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'pallet_id' => $first->id])
            ->assertOk()->assertJsonPath('already_packed', true)
            ->assertJsonPath('message', "Unit SN-{$wo->id}-1 is already packed - nothing changed.");
        $this->assertSame(1.0, (float) $pack->fresh()->passed_qty);
        $this->assertSame(1, $first->fresh()->qty);
        $this->assertSame(1, SerialUnit::where('psn', "P-{$wo->id}-1")->first()->history()->where('parameters->event', 'packed')->count(), 'a rescan writes no second packed event');

        // Aiming the same unit at another pallet is refused, so no pallet's count drifts.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'pallet_id' => $second->id])
            ->assertStatus(422)->assertJsonPath('message', "Unit SN-{$wo->id}-1 is already on pallet {$first->pallet_no}.");
        $this->assertSame(0, $second->fresh()->qty);
        $this->assertSame($first->id, SerialUnit::where('psn', "P-{$wo->id}-1")->value('pallet_id'));
    }

    public function test_a_unit_without_a_process_serial_is_packed_by_its_serial_number(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5]);
        SerialUnit::create(['serial_no' => 'ONLY-SN-1', 'psn' => null, 'work_order_id' => $wo->id]);

        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => 'only-sn-1'])
            ->assertOk()->assertJsonPath('unit.serial_no', 'ONLY-SN-1');
        $this->assertNotNull(SerialUnit::where('serial_no', 'ONLY-SN-1')->value('packed_at'));
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => 'NOPE'])->assertNotFound();
    }

    public function test_a_unit_does_not_go_into_another_orders_carton_or_pallet(): void
    {
        $wo = $this->packableOrder(['unit' => 'carton', 'carton_capacity' => 5]);
        $other = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $othersPallet = Pallet::create(['work_order_id' => $other->id, 'status' => 'open', 'qty' => 0]);
        $othersCarton = $this->actingAs($this->operator)->postJson('/packaging/cartons', ['work_order_id' => $other->id])->assertCreated()->json('carton');

        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'pallet_id' => $othersPallet->id])->assertStatus(422);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => "P-{$wo->id}-1", 'carton_id' => $othersCarton['id']])->assertStatus(422);
        $this->assertNull(SerialUnit::where('psn', "P-{$wo->id}-1")->value('packed_at'));
        $this->assertSame(0, $othersPallet->fresh()->qty);
    }

    public function test_the_station_lists_open_packing_steps_with_their_config(): void
    {
        $template = $this->templateWithPacking();
        $wo = WorkOrder::factory()->create(['product_type_id' => $template->product_type_id, 'process_snapshot' => $template->toSnapshot(), 'planned_qty' => 4, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        app(WorkOrderService::class)->createBatch($wo, 4);

        $this->actingAs($this->operator)->getJson(route('packaging.packing-steps'))
            ->assertOk()
            ->assertJsonCount(1, 'steps')
            ->assertJsonPath('steps.0.order_no', $wo->order_no)
            ->assertJsonPath('steps.0.config.carton_capacity', 2)
            ->assertJsonPath('steps.0.target_qty', 4);
    }
}
