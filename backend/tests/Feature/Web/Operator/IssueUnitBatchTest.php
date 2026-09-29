<?php

namespace Tests\Feature\Web\Operator;

use App\Models\LabelTemplate;
use App\Models\LotSequence;
use App\Models\ProductType;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Numbers issued ahead of the line: a batch of units with printable labels, applied later by scanning. */
class IssueUnitBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private WorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $product = ProductType::factory()->create();
        $this->wo = WorkOrder::factory()->create(['product_type_id' => $product->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        LabelTemplate::create(['name' => 'SN', 'type' => LabelTemplate::TYPE_SERIAL_UNIT, 'size' => '80x40', 'barcode_format' => 'code128', 'fields_config' => ['serial_no' => true, 'psn' => true, 'qr' => true, 'barcode' => true], 'is_default' => true, 'is_active' => true]);
    }

    private function sequence(string $purpose, string $pattern): LotSequence
    {
        return LotSequence::create(['name' => $purpose, 'product_type_id' => $this->wo->product_type_id, 'purpose' => $purpose, 'prefix' => '', 'pattern' => $pattern, 'pad_size' => 4, 'next_number' => 1, 'reset_period' => 'none']);
    }

    public function test_a_batch_registers_units_with_both_numbers_and_prints_their_labels_in_one_file(): void
    {
        $this->sequence(LotSequence::PURPOSE_UNIT_SERIAL, 'SN[seq]');
        $this->sequence(LotSequence::PURPOSE_PROCESS_SERIAL, 'P-[seq]');

        $res = $this->actingAs($this->operator)->postJson(route('operator.unit-labels.issue-batch'), ['work_order_id' => $this->wo->id, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonPath('count', 3)
            ->assertJsonPath('units.0.serial_no', 'SN0001')
            ->assertJsonPath('units.0.psn', 'P-0001')
            ->assertJsonPath('units.2.serial_no', 'SN0003');

        $this->assertSame(3, SerialUnit::where('work_order_id', $this->wo->id)->count());
        $unit = SerialUnit::where('serial_no', 'SN0002')->firstOrFail();
        $this->assertSame(SerialUnit::STATUS_IN_PRODUCTION, $unit->status);
        $this->assertSame('issued', $unit->history()->first()->parameters['event']);

        // The labels: one PDF with the three, in the order issued, and the ZPL twin.
        $pdfUrl = $res->json('label_pdf');
        $this->assertStringContainsString('/packaging/labels/serial-units/pdf?ids=', $pdfUrl);
        $this->actingAs($this->operator)->get($pdfUrl)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->operator)->get($res->json('label_zpl'))->assertOk()->assertHeader('content-type', 'application/zpl');
        $this->actingAs($this->operator)->get('/packaging/labels/serial-units/pdf')->assertStatus(422);

        // On the line the pre-printed label is scanned: applying it binds the same numbers without complaint.
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.apply'), ['serial_no' => 'SN0002', 'psn' => 'P-0002', 'work_order_id' => $this->wo->id])
            ->assertOk();
        $this->assertSame('label_applied', $unit->history()->reorder('id', 'desc')->first()->parameters['event']);
        $this->assertSame(3, SerialUnit::count(), 'scanning a pre-issued label registers nothing new');
    }

    public function test_a_spaced_pattern_is_stored_the_way_its_label_scans(): void
    {
        $this->sequence(LotSequence::PURPOSE_UNIT_SERIAL, 'LBL [seq] Z');
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.issue-batch'), ['work_order_id' => $this->wo->id, 'quantity' => 1])
            ->assertCreated()->assertJsonPath('units.0.serial_no', 'LBL0001Z');
        // The printed label reads "LBL 0001 Z"; the scanner sends it without spaces and finds the unit.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => 'LBL 0001 Z'])->assertOk();
    }

    public function test_a_number_already_on_a_product_refuses_the_whole_batch(): void
    {
        $this->sequence(LotSequence::PURPOSE_UNIT_SERIAL, 'SN[seq]');
        SerialUnit::create(['serial_no' => 'SN0002', 'status' => SerialUnit::STATUS_SHIPPED]);

        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.issue-batch'), ['work_order_id' => $this->wo->id, 'quantity' => 3])
            ->assertStatus(422)->assertJsonPath('message', 'Serial SN0002 already exists - check the sequence before issuing more numbers.');
        $this->assertSame(1, SerialUnit::count(), 'nothing from the batch is kept, not even SN0001');
        $this->assertSame(0, SerialUnit::where('serial_no', 'SN0002')->first()->history()->count(), 'the shipped unit got no issued event');
    }

    public function test_the_batch_refuses_what_it_cannot_number_and_validates_its_input(): void
    {
        // A guest first: actingAs() sticks for the rest of the test.
        $this->postJson(route('operator.unit-labels.issue-batch'), ['work_order_id' => $this->wo->id, 'quantity' => 2])->assertUnauthorized();

        // No unit-serial sequence: nothing to print.
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.issue-batch'), ['work_order_id' => $this->wo->id, 'quantity' => 2])
            ->assertStatus(422);
        $this->assertSame(0, SerialUnit::count());

        // A unit-serial sequence but no process-serial one: fine unless the plant requires PSNs.
        $this->sequence(LotSequence::PURPOSE_UNIT_SERIAL, 'SN[seq]');
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.issue-batch'), ['work_order_id' => $this->wo->id, 'quantity' => 2])
            ->assertCreated()->assertJsonPath('units.0.psn', null);

        DB::table('system_settings')->updateOrInsert(['key' => 'unit_psn_required'], ['value' => json_encode(true)]);
        app(\App\Support\UnitSerialisation::class)->forget(); // the settings are cached per request
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.issue-batch'), ['work_order_id' => $this->wo->id, 'quantity' => 2])
            ->assertStatus(422);

        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.issue-batch'), ['work_order_id' => $this->wo->id, 'quantity' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.issue-batch'), ['quantity' => 2])
            ->assertStatus(422)->assertJsonValidationErrors('work_order_id');
    }
}
