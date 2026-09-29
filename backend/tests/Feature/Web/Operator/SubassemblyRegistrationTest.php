<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Production\OperatorScreens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A sub-assembly registered by its own serial where it is made, then scanned
 * into the product: the product is linked to a unit with a history.
 */
class SubassemblyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private WorkOrder $subOrder;

    private Material $module;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $line = Line::factory()->create();
        $subProduct = ProductType::factory()->create(['code' => 'SUB-P']);
        $routing = ProcessTemplate::factory()->create(['product_type_id' => $subProduct->id, 'is_active' => true]);
        $type = MaterialType::firstOrCreate(['code' => 'semi_finished'], ['name' => 'Semi-finished']);
        $this->module = Material::factory()->create(['code' => 'MOD-A', 'name' => 'Module A', 'material_type_id' => $type->id,
            'tracking_type' => 'serial', 'is_manufactured' => true, 'producing_process_template_id' => $routing->id]);
        $this->subOrder = WorkOrder::factory()->create(['line_id' => $line->id, 'product_type_id' => $subProduct->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
    }

    private function register(array $body)
    {
        return $this->actingAs($this->operator)->postJson(route('operator.unit-labels.subassembly'), $body);
    }

    public function test_a_sub_assembly_is_registered_and_linked_when_scanned_into_a_product(): void
    {
        $this->register(['work_order_id' => $this->subOrder->id, 'material_id' => $this->module->id, 'serial_no' => 'mod-000123'])
            ->assertCreated()->assertJsonPath('unit.serial_no', 'MOD-000123');

        $part = SerialUnit::where('serial_no', 'MOD-000123')->firstOrFail();
        $this->assertSame([$this->module->id, $this->subOrder->id, SerialUnit::STATUS_COMPLETED], [$part->material_id, $part->work_order_id, $part->status]);
        $this->assertSame('subassembly_registered', $part->history()->first()->parameters['event']);

        // Later, on the main line: the product gets the part by its serial.
        $product = SerialUnit::create(['serial_no' => 'PRD-1', 'psn' => 'P-1']);
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.component'), ['serial_no' => 'PRD-1', 'identifier' => 'MOD-000123'])->assertSuccessful();
        $component = $product->components()->firstOrFail();
        $this->assertSame([$part->id, $this->module->id], [$component->component_serial_unit_id, $component->material_id]);

        // Its own trace says where it went.
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin)->get('/admin/traceability?q=MOD-000123')->assertOk()
            ->assertInertia(fn ($page) => $page->where('result.type', 'serial')->where('result.installed_in.0.serial_no', 'PRD-1'));
    }

    public function test_a_serial_is_registered_once_and_only_for_what_the_order_makes(): void
    {
        $this->register(['work_order_id' => $this->subOrder->id, 'material_id' => $this->module->id, 'serial_no' => 'MOD-1'])->assertCreated();
        $this->register(['work_order_id' => $this->subOrder->id, 'material_id' => $this->module->id, 'serial_no' => 'MOD-1'])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'MOD-1'));

        $other = Material::factory()->create(['code' => 'BOLT', 'tracking_type' => 'serial']);
        $this->register(['work_order_id' => $this->subOrder->id, 'material_id' => $other->id, 'serial_no' => 'B-1'])->assertUnprocessable();
        $this->register(['work_order_id' => $this->subOrder->id, 'material_id' => $this->module->id])->assertJsonValidationErrors('serial_no');
        $this->assertSame(1, SerialUnit::count());
    }

    public function test_guests_cannot_register_and_the_station_lists_what_each_order_makes(): void
    {
        $this->postJson(route('operator.unit-labels.subassembly'), ['work_order_id' => $this->subOrder->id, 'material_id' => $this->module->id, 'serial_no' => 'X'])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('operator.unit-labels.subassembly'), ['work_order_id' => $this->subOrder->id, 'material_id' => $this->module->id, 'serial_no' => 'X'])->assertForbidden();

        $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->subOrder->line_id])->get(route('operator.unit-labels.station'))
            ->assertInertia(fn ($page) => $page->where('workOrders.0.produces.0.code', 'MOD-A'));

        // The bench making it gets the label station without anyone pinning it.
        $productId = (int) $this->subOrder->product_type_id;
        $this->assertContains($productId, (fn () => $this->serialisedProducts([$productId]))->call(app(OperatorScreens::class)));
    }
}
