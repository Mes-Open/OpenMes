<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\SerialUnit;
use App\Models\SerialUnitComponent;
use App\Models\User;
use App\Services\Traceability\BindingException;
use App\Services\Traceability\SerialTraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Scanning components into a serialised unit, and tracing them back out. */
class UnitComponentBindingTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        Role::findOrCreate('Admin', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function bind(array $payload)
    {
        return $this->actingAs($this->operator)->postJson(route('operator.unit-labels.component'), $payload);
    }

    public function test_a_bare_identifier_binds_with_the_chosen_material(): void
    {
        SerialUnit::create(['serial_no' => 'UNIT-1']);
        $part = Material::factory()->create(['code' => 'CMP']);

        $this->bind(['serial_no' => 'unit-1', 'identifier' => ' cmp-01 ', 'material_id' => $part->id])
            ->assertOk()
            ->assertJsonPath('component.identifier', 'CMP-01')
            ->assertJsonPath('component.kind', 'identifier')
            ->assertJsonPath('component.material.code', 'CMP');

        $unit = SerialUnit::where('serial_no', 'UNIT-1')->firstOrFail();
        $this->assertSame('component_bound', $unit->history()->firstOrFail()->parameters['event']);
        $this->assertSame(1, $unit->components()->installed()->count());
    }

    public function test_a_held_sub_assembly_is_not_installed_and_a_shipped_product_keeps_its_parts(): void
    {
        SerialUnit::create(['serial_no' => 'UNIT-H']);
        SerialUnit::create(['serial_no' => 'BOARD-H', 'status' => SerialUnit::STATUS_BLOCKED]);
        $this->bind(['serial_no' => 'UNIT-H', 'identifier' => 'BOARD-H'])->assertStatus(422);
        $this->assertSame(0, SerialUnit::where('serial_no', 'UNIT-H')->first()->components()->count());

        $this->bind(['serial_no' => 'UNIT-H', 'identifier' => 'PART-1'])->assertOk();
        $component = SerialUnitComponent::where('identifier', 'PART-1')->firstOrFail();
        SerialUnit::where('serial_no', 'UNIT-H')->update(['status' => SerialUnit::STATUS_SHIPPED]);
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.component-unbind', $component), ['reason' => 'late change'])->assertStatus(422);
        $this->assertNull($component->fresh()->unbound_at);
    }

    public function test_the_identifier_resolves_to_a_lot_or_a_serialised_sub_assembly(): void
    {
        SerialUnit::create(['serial_no' => 'UNIT-1']);
        $lot = MaterialLot::factory()->create(['lot_number' => 'PAINT-L7']);
        $pcba = SerialUnit::create(['serial_no' => 'SUB-01']);

        $this->bind(['serial_no' => 'UNIT-1', 'identifier' => 'PAINT-L7'])->assertOk()->assertJsonPath('component.kind', 'material_lot');
        $this->bind(['serial_no' => 'UNIT-1', 'identifier' => 'SUB-01'])->assertOk()->assertJsonPath('component.kind', 'serial_unit');

        $rows = SerialUnitComponent::orderBy('id')->get();
        $this->assertSame($lot->id, $rows[0]->material_lot_id);
        $this->assertSame($lot->material_id, $rows[0]->material_id);
        $this->assertSame($pcba->id, $rows[1]->component_serial_unit_id);
    }

    public function test_a_sub_assembly_is_in_one_unit_at_a_time_until_unbound(): void
    {
        SerialUnit::create(['serial_no' => 'UNIT-1']);
        SerialUnit::create(['serial_no' => 'UNIT-2']);
        SerialUnit::create(['serial_no' => 'SUB-01']);

        $this->bind(['serial_no' => 'UNIT-1', 'identifier' => 'SUB-01'])->assertOk();
        $this->bind(['serial_no' => 'UNIT-2', 'identifier' => 'SUB-01'])->assertUnprocessable();

        $svc = app(SerialTraceService::class);
        $component = SerialUnitComponent::firstOrFail();
        $svc->unbindComponent($component, $this->admin, 'Board swapped at repair');
        $this->assertNotNull($component->fresh()->unbound_at);

        $this->bind(['serial_no' => 'UNIT-2', 'identifier' => 'SUB-01'])->assertOk();
    }

    public function test_the_same_scan_twice_is_one_component(): void
    {
        SerialUnit::create(['serial_no' => 'UNIT-1']);

        $this->bind(['serial_no' => 'UNIT-1', 'identifier' => 'CMP-01'])->assertOk();
        $this->bind(['serial_no' => 'UNIT-1', 'identifier' => 'CMP-01'])->assertOk();

        $this->assertSame(1, SerialUnitComponent::count());
    }

    public function test_refusals(): void
    {
        SerialUnit::create(['serial_no' => 'UNIT-GONE', 'status' => SerialUnit::STATUS_SHIPPED]);
        SerialUnit::create(['serial_no' => 'UNIT-1']);

        $this->bind(['serial_no' => 'NOPE', 'identifier' => 'CMP-01'])->assertNotFound();
        $this->bind(['serial_no' => 'UNIT-GONE', 'identifier' => 'CMP-01'])->assertUnprocessable();
        $this->bind(['serial_no' => 'UNIT-1', 'identifier' => 'UNIT-1'])->assertUnprocessable();
        $this->bind(['serial_no' => 'UNIT-1'])->assertUnprocessable()->assertJsonValidationErrors(['identifier']);

        $this->expectException(BindingException::class);
        app(SerialTraceService::class)->bindComponent(SerialUnit::where('serial_no', 'UNIT-1')->firstOrFail(), '', $this->operator);
    }

    public function test_a_lot_typed_in_lower_case_or_with_spaces_still_resolves(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'UNIT-9']);
        $material = Material::factory()->create(['code' => 'PCB']);
        $lot = MaterialLot::create(['lot_number' => 'ab-2026 x1', 'material_id' => $material->id, 'quantity_received' => 10, 'quantity_available' => 10, 'unit_of_measure' => 'pcs', 'received_at' => now(), 'status' => 'received']);

        $this->bind(['serial_no' => 'UNIT-9', 'identifier' => 'AB-2026 X1'])->assertOk()->assertJsonPath('component.kind', 'material_lot');
        $component = SerialUnitComponent::where('serial_unit_id', $unit->id)->firstOrFail();
        $this->assertSame($lot->id, $component->material_lot_id);
        $this->assertSame($material->id, $component->material_id);
    }

    public function test_the_lots_own_trace_lists_the_units_it_went_into(): void
    {
        $material = Material::factory()->create(['code' => 'CMP']);
        MaterialLot::create(['lot_number' => 'CMP-LOT-9', 'material_id' => $material->id, 'quantity_received' => 10, 'quantity_available' => 10, 'unit_of_measure' => 'pcs', 'received_at' => now(), 'status' => 'received']);
        $unit = SerialUnit::create(['serial_no' => 'UNIT-20', 'psn' => 'P-20']);
        app(SerialTraceService::class)->bindComponent($unit, 'CMP-LOT-9', $this->operator);

        $this->actingAs($this->admin)->get('/admin/traceability?q=CMP-LOT-9')
            ->assertInertia(fn ($page) => $page
                ->where('result.type', 'material_lot')
                ->where('result.units.0.serial_no', 'UNIT-20')
                ->where('result.units.0.installed', true));
    }

    public function test_a_unit_cannot_contain_what_it_is_inside_of(): void
    {
        $a = SerialUnit::create(['serial_no' => 'A']);
        $b = SerialUnit::create(['serial_no' => 'B']);
        $c = SerialUnit::create(['serial_no' => 'C']);
        $svc = app(SerialTraceService::class);
        $svc->bindComponent($a, 'B', $this->operator);
        $svc->bindComponent($b, 'C', $this->operator);

        // C is inside B, which is inside A: A cannot be a component of C.
        $this->bind(['serial_no' => 'C', 'identifier' => 'A'])->assertStatus(422);
        $this->bind(['serial_no' => 'B', 'identifier' => 'A'])->assertStatus(422);
        $this->assertSame(0, SerialUnitComponent::where('component_serial_unit_id', $a->id)->count());
    }

    public function test_the_station_takes_a_component_out_with_a_reason(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'UNIT-7']);
        $part = Material::factory()->create(['code' => 'CMP']);
        $component = app(SerialTraceService::class)->bindComponent($unit, 'CMP-LOT-1', $this->operator, ['material_id' => $part->id]);

        // A guest first: actingAs() sticks for the rest of the test.
        $this->postJson(route('operator.unit-labels.component-unbind', $component), ['reason' => 'swapped'])->assertUnauthorized();

        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.component-unbind', $component), ['reason' => 'Part swapped at repair'])
            ->assertOk()
            ->assertJsonPath('unit.serial_no', 'UNIT-7')
            ->assertJsonCount(0, 'components');

        $component->refresh();
        $this->assertNotNull($component->unbound_at);
        $this->assertSame($this->operator->id, $component->unbound_by_id);
        $this->assertSame('Part swapped at repair', $component->unbind_reason);
        $last = $unit->history()->reorder('id', 'desc')->first();
        $this->assertSame('component_unbound', $last->parameters['event']);
        $this->assertSame('Part swapped at repair', $last->notes);

        // The same lot can now go into another unit, and the old unit's list is empty.
        SerialUnit::create(['serial_no' => 'UNIT-8']);
        $this->bind(['serial_no' => 'UNIT-8', 'identifier' => 'CMP-LOT-1', 'material_id' => $part->id])->assertOk();
        $this->actingAs($this->operator)->getJson(route('operator.unit-labels.components', ['serial_no' => 'UNIT-7']))->assertJsonCount(0, 'components');
    }

    public function test_guest_cannot_bind(): void
    {
        $this->postJson(route('operator.unit-labels.component'), ['serial_no' => 'UNIT-1', 'identifier' => 'X'])->assertUnauthorized();
    }

    public function test_components_listing_for_the_station(): void
    {
        SerialUnit::create(['serial_no' => 'UNIT-1']);
        $this->bind(['serial_no' => 'UNIT-1', 'identifier' => 'CMP-01'])->assertOk();

        $this->actingAs($this->operator)
            ->getJson(route('operator.unit-labels.components', ['serial_no' => 'unit-1']))
            ->assertOk()
            ->assertJsonPath('unit.serial_no', 'UNIT-1')
            ->assertJsonCount(1, 'components')
            ->assertJsonPath('components.0.identifier', 'CMP-01');
    }

    public function test_api_binds_a_component(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'UNIT-1']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/serial-units/{$unit->id}/components", ['identifier' => 'cmp-01', 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.identifier', 'CMP-01')
            ->assertJsonPath('data.quantity', '2.0000');
    }

    public function test_the_console_traces_a_component_to_its_units_and_shows_a_units_components(): void
    {
        SerialUnit::create(['serial_no' => 'UNIT-1']);
        SerialUnit::create(['serial_no' => 'UNIT-2']);
        $this->bind(['serial_no' => 'UNIT-1', 'identifier' => 'CMP-01'])->assertOk();
        $this->bind(['serial_no' => 'UNIT-2', 'identifier' => 'CMP-01'])->assertOk();
        app(SerialTraceService::class)->unbindComponent(SerialUnitComponent::where('serial_unit_id', SerialUnit::where('serial_no', 'UNIT-2')->value('id'))->firstOrFail(), $this->admin, 'Swapped');

        $this->actingAs($this->admin)
            ->get(route('admin.traceability.index', ['q' => 'CMP-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('result.type', 'component')
                ->where('result.data.identifier', 'CMP-01')
                ->has('result.data.units', 2)
                ->where('result.data.units.0.serial_no', 'UNIT-2')
                ->where('result.data.units.0.installed', false)
                ->where('result.data.units.1.installed', true));

        $this->actingAs($this->admin)
            ->get(route('admin.traceability.index', ['q' => 'UNIT-1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('result.type', 'serial')
                ->has('result.installed', 1)
                ->where('result.installed.0.identifier', 'CMP-01')
                ->where('result.installed.0.kind', 'identifier'));
    }
}
