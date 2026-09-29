<?php

namespace Tests\Feature\Web\Operator;

use App\Models\LabelTemplate;
use App\Models\LotSequence;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A unit numbered by its process serial at the first station, with components
 * bound to that number, gets its serial number later with the product label -
 * and stays one unit all the way.
 */
class PsnFirstUnitTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private WorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        Role::findOrCreate('Admin', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        LotSequence::create(['name' => 'PSN', 'product_type_id' => $this->wo->product_type_id, 'purpose' => LotSequence::PURPOSE_PROCESS_SERIAL, 'prefix' => '', 'pattern' => 'P-[seq]', 'pad_size' => 4, 'next_number' => 1, 'reset_period' => 'none']);
    }

    private function send(string $route, array $body, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->operator)->postJson(route($route), $body);
    }

    public function test_a_unit_starts_on_its_psn_takes_components_and_gets_its_sn_later(): void
    {
        // Guest first: actingAs() sticks for the rest of the test.
        $this->postJson(route('operator.unit-labels.start'), ['work_order_id' => $this->wo->id])->assertUnauthorized();

        LabelTemplate::create(['name' => 'Unit', 'type' => LabelTemplate::TYPE_SERIAL_UNIT, 'size' => '50x30', 'barcode_format' => 'code128', 'fields_config' => LabelTemplate::defaultFieldsFor(LabelTemplate::TYPE_SERIAL_UNIT), 'is_default' => true, 'is_active' => true]);

        // First station: the next PSN from the product's sequence, and its label.
        $started = $this->send('operator.unit-labels.start', ['work_order_id' => $this->wo->id])->assertCreated()
            ->assertJsonPath('unit.psn', 'P-0001')->assertJsonPath('unit.serial_no', null)->assertJsonPath('unit.display_id', 'P-0001')->json();
        $this->actingAs($this->operator)->get($started['label_pdf'])->assertOk();
        $unit = SerialUnit::findOrFail($started['unit']['id']);
        $this->assertSame($this->wo->id, $unit->work_order_id);
        $this->assertSame('started', $unit->history()->first()->parameters['event']);

        // Assembly: a component bound to the unit by its PSN.
        $this->send('operator.unit-labels.component', ['serial_no' => 'p-0001', 'identifier' => 'CMP-01'])->assertOk();
        $this->actingAs($this->operator)->getJson(route('operator.unit-labels.components', ['serial_no' => 'P-0001']))
            ->assertOk()->assertJsonPath('unit.id', $unit->id)->assertJsonCount(1, 'components');

        // The product label: the SN goes onto the same unit.
        $this->send('operator.unit-labels.apply', ['serial_no' => 'SN-1', 'psn' => 'P-0001', 'work_order_id' => $this->wo->id])->assertOk()
            ->assertJsonPath('unit.id', $unit->id)->assertJsonPath('unit.serial_no', 'SN-1');
        $this->assertSame(1, SerialUnit::count());
        $this->assertSame(1, $unit->fresh()->components()->count());
    }

    public function test_a_scanned_psn_starts_a_unit_once(): void
    {
        $this->send('operator.unit-labels.start', ['psn' => ' p-0777 '])->assertCreated()->assertJsonPath('unit.psn', 'P-0777');
        $this->send('operator.unit-labels.start', ['psn' => 'P-0777'])->assertStatus(422);
        // No PSN and no order: nothing to number from.
        $this->send('operator.unit-labels.start', [])->assertStatus(422)->assertJsonValidationErrors('work_order_id');
    }

    public function test_an_sn_already_on_another_unit_is_refused_for_a_waiting_unit(): void
    {
        $this->send('operator.unit-labels.start', ['psn' => 'P-0100'])->assertCreated();
        SerialUnit::create(['serial_no' => 'SN-TAKEN', 'psn' => 'P-0200']);

        $this->send('operator.unit-labels.apply', ['serial_no' => 'SN-TAKEN', 'psn' => 'P-0100'])->assertStatus(422);
        $this->assertNull(SerialUnit::where('psn', 'P-0100')->value('serial_no'));
    }

    public function test_a_psn_batch_starts_units_without_a_serial_number(): void
    {
        $this->send('operator.unit-labels.issue-batch', ['work_order_id' => $this->wo->id, 'quantity' => 3, 'psn_only' => true])->assertCreated()
            ->assertJsonPath('count', 3)->assertJsonPath('units.0.serial_no', null)->assertJsonPath('units.2.psn', 'P-0003');
        $this->assertSame(3, SerialUnit::whereNull('serial_no')->where('work_order_id', $this->wo->id)->count());
    }

    public function test_the_tester_puts_the_sn_onto_the_waiting_unit(): void
    {
        $this->send('operator.unit-labels.start', ['psn' => 'P-0300'])->assertCreated();
        $tester = User::factory()->create();
        $tester->assignRole('Admin');

        $this->withHeader('Authorization', 'Bearer '.$tester->createToken('tester')->plainTextToken)
            ->postJson('/api/v1/test-runs', ['serial_no' => 'SN-300', 'psn' => 'P-0300', 'verdict' => 'pass'])
            ->assertCreated();

        $this->assertSame(1, SerialUnit::count());
        $this->assertSame('SN-300', SerialUnit::where('psn', 'P-0300')->value('serial_no'));
    }

    public function test_the_api_registers_a_unit_on_its_psn_alone(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson('/api/v1/serial-units', ['psn' => 'P-0400'])->assertCreated()->assertJsonPath('data.serial_no', null);
        $this->actingAs($admin)->postJson('/api/v1/serial-units', [])->assertStatus(422)->assertJsonValidationErrors(['serial_no']);
    }
}
