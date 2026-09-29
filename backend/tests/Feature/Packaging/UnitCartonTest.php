<?php

namespace Tests\Feature\Packaging;

use App\Enums\PalletStatus;
use App\Models\LabelTemplate;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\UnitCarton;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\LabelTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Serialised units after the line: carton, pallet, shipment. */
class UnitCartonTest extends TestCase
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
        $this->wo = WorkOrder::factory()->create();
    }

    private function hit(string $route, array $params = [], array $body = [])
    {
        return $this->actingAs($this->operator)->postJson(route($route, $params), $body);
    }

    public function test_carton_lifecycle_open_fill_close_label(): void
    {
        $this->seed(LabelTemplatesSeeder::class);
        SerialUnit::create(['serial_no' => 'SN-1', 'psn' => 'P-1', 'work_order_id' => $this->wo->id]);
        SerialUnit::create(['serial_no' => 'SN-2', 'psn' => 'P-2', 'work_order_id' => $this->wo->id]);

        $carton = $this->hit('packaging.cartons.store', [], ['work_order_id' => $this->wo->id])
            ->assertCreated()->assertJsonPath('carton.status', 'open')->json('carton');
        $this->assertSame('CTN-000001', $carton['carton_no']);

        // Empty cartons can't be closed - nothing to label.
        $this->hit('packaging.cartons.close', ['carton' => $carton['id']])->assertUnprocessable();

        $this->hit('packaging.scan-unit', [], ['psn' => 'P-1', 'carton_id' => $carton['id']])
            ->assertOk()->assertJsonPath('unit.carton.carton_no', 'CTN-000001')->assertJsonPath('unit.status', 'completed');
        $this->hit('packaging.scan-unit', [], ['psn' => 'P-2', 'carton_id' => $carton['id']])->assertOk();
        // Scanning the same unit twice does not count it twice.
        $this->hit('packaging.scan-unit', [], ['psn' => 'P-2', 'carton_id' => $carton['id']])->assertOk();

        $this->actingAs($this->operator)->getJson(route('packaging.cartons.show', ['carton' => $carton['id']]))
            ->assertOk()->assertJsonPath('carton.qty', 2)->assertJsonCount(2, 'units');

        $closed = $this->hit('packaging.cartons.close', ['carton' => $carton['id']])
            ->assertOk()->assertJsonPath('carton.status', 'closed')->json();
        $this->assertNotNull(UnitCarton::find($carton['id'])->closed_at);

        // Closed: no more units in, and the label lists the two serials.
        SerialUnit::create(['serial_no' => 'SN-3', 'psn' => 'P-3']);
        $this->hit('packaging.scan-unit', [], ['psn' => 'P-3', 'carton_id' => $carton['id']])->assertUnprocessable();
        $this->assertTrue(LabelTemplate::where('type', LabelTemplate::TYPE_CARTON)->exists());
        $this->actingAs($this->operator)->get($closed['label_pdf'])->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_unit_is_in_one_carton_at_a_time(): void
    {
        SerialUnit::create(['serial_no' => 'SN-1', 'psn' => 'P-1']);
        $a = UnitCarton::create(['status' => 'open']);
        $b = UnitCarton::create(['status' => 'open']);

        $this->hit('packaging.scan-unit', [], ['psn' => 'P-1', 'carton_id' => $a->id])->assertOk();
        $this->hit('packaging.scan-unit', [], ['psn' => 'P-1', 'carton_id' => $b->id])->assertUnprocessable();
    }

    public function test_putting_a_carton_on_a_pallet_moves_its_units_and_counts_the_pieces(): void
    {
        $pallet = Pallet::create(['work_order_id' => $this->wo->id, 'qty' => 0, 'status' => PalletStatus::Open->value]);
        $carton = UnitCarton::create(['status' => 'open', 'work_order_id' => $this->wo->id]);
        foreach (['P-1', 'P-2', 'P-3'] as $i => $psn) {
            SerialUnit::create(['serial_no' => "SN-$i", 'psn' => $psn]);
            $this->hit('packaging.scan-unit', [], ['psn' => $psn, 'carton_id' => $carton->id])->assertOk();
        }

        $this->hit('packaging.cartons.pallet', ['carton' => $carton->id], ['pallet_id' => $pallet->id])
            ->assertOk()->assertJsonPath('carton.pallet_no', $pallet->pallet_no);

        $this->assertSame(3, SerialUnit::where('pallet_id', $pallet->id)->count());
        $this->assertSame(3, (int) $pallet->fresh()->qty);
        $this->assertSame('palletised', SerialUnit::where('psn', 'P-1')->firstOrFail()->history()->reorder('id', 'desc')->firstOrFail()->parameters['event']);

        // A unit scanned into the carton after that lands on the pallet too, and counts.
        SerialUnit::create(['serial_no' => 'SN-9', 'psn' => 'P-9']);
        $this->hit('packaging.scan-unit', [], ['psn' => 'P-9', 'carton_id' => $carton->id])->assertOk()->assertJsonPath('unit.pallet_no', $pallet->pallet_no);
        $this->assertSame(4, (int) $pallet->fresh()->qty);
    }

    public function test_a_carton_of_tested_units_leaves_the_pallet_it_goes_on_ready_to_ship(): void
    {
        $pallet = Pallet::create(['work_order_id' => $this->wo->id, 'qty' => 0, 'status' => PalletStatus::Open->value]);
        $carton = UnitCarton::create(['status' => 'open', 'work_order_id' => $this->wo->id]);
        foreach (['P-1', 'P-2'] as $i => $psn) {
            $unit = SerialUnit::create(['serial_no' => "SN-$i", 'psn' => $psn]);
            app(\App\Services\Traceability\SerialTraceService::class)->recordStep($unit, $this->operator, null, ['result' => 'pass', 'parameters' => ['event' => 'test']]);
            $this->hit('packaging.scan-unit', [], ['psn' => $psn, 'carton_id' => $carton->id])->assertOk();
        }

        $this->hit('packaging.cartons.pallet', ['carton' => $carton->id], ['pallet_id' => $pallet->id])->assertOk();

        // The pallet's quality is its units' - judged once they are on it, not before.
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->fresh()->quality_status);
    }

    public function test_shipping_the_pallet_ships_every_unit_on_it_except_scrap(): void
    {
        $pallet = Pallet::create(['work_order_id' => $this->wo->id, 'qty' => 0, 'status' => PalletStatus::Open->value]);
        $good = SerialUnit::create(['serial_no' => 'SN-1', 'psn' => 'P-1']);
        // Tested and passed before packing: that is what lets the pallet ship.
        app(\App\Services\Traceability\SerialTraceService::class)->recordStep($good, $this->operator, null, ['result' => 'pass', 'parameters' => ['event' => 'test']]);
        $this->hit('packaging.scan-unit', [], ['psn' => 'P-1', 'pallet_id' => $pallet->id])->assertOk();
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->fresh()->quality_status);
        $scrap = SerialUnit::create(['serial_no' => 'SN-X', 'pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_SCRAPPED]);

        $this->actingAs($this->operator);
        $pallet->fresh()->update(['status' => PalletStatus::Shipped->value]);

        $this->assertSame(SerialUnit::STATUS_SHIPPED, $good->fresh()->status);
        $this->assertNotNull($good->fresh()->shipped_at);
        $this->assertSame('shipped', $good->history()->reorder('id', 'desc')->firstOrFail()->parameters['event']);
        $this->assertSame(SerialUnit::STATUS_SCRAPPED, $scrap->fresh()->status);
    }

    public function test_guest_cannot_open_a_carton(): void
    {
        $this->postJson(route('packaging.cartons.store'), [])->assertUnauthorized();
    }

    public function test_pallet_quality_follows_the_units_test_verdicts(): void
    {
        $pallet = Pallet::create(['work_order_id' => $this->wo->id, 'qty' => 0, 'status' => PalletStatus::Open->value]);
        $svc = app(\App\Services\Traceability\SerialTraceService::class);
        $a = SerialUnit::create(['serial_no' => 'SN-A', 'psn' => 'P-A']);
        $b = SerialUnit::create(['serial_no' => 'SN-B', 'psn' => 'P-B']);
        $svc->recordStep($a, $this->operator, null, ['result' => 'pass', 'parameters' => ['event' => 'test']]);

        // One tested unit, one never tested: the pallet is still pending.
        $this->hit('packaging.scan-unit', [], ['psn' => 'P-A', 'pallet_id' => $pallet->id])->assertOk();
        $this->hit('packaging.scan-unit', [], ['psn' => 'P-B', 'pallet_id' => $pallet->id])->assertOk();
        $this->assertSame(Pallet::QUALITY_PENDING, $pallet->fresh()->quality_status);

        // The second unit passes its test after it was packed: the pallet passes.
        $svc->recordStep($b, $this->operator, null, ['result' => 'pass', 'parameters' => ['event' => 'test']]);
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->fresh()->quality_status);

        // A later fail on any unit fails the pallet - and the ship gate refuses it.
        $svc->recordStep($b, $this->operator, null, ['result' => 'fail', 'parameters' => ['event' => 'test']]);
        $this->assertSame(Pallet::QUALITY_FAIL, $pallet->fresh()->quality_status);
        $this->expectException(\DomainException::class);
        $pallet->fresh()->update(['status' => PalletStatus::Shipped->value]);
    }

    public function test_station_stats_count_serialised_units_as_packed(): void
    {
        SerialUnit::create(['serial_no' => 'SN-1', 'psn' => 'P-1', 'work_order_id' => $this->wo->id]);
        SerialUnit::create(['serial_no' => 'SN-2', 'psn' => 'P-2', 'work_order_id' => $this->wo->id]);
        $this->wo->update(['status' => WorkOrder::STATUS_IN_PROGRESS, 'planned_qty' => 10]);

        $this->hit('packaging.scan-unit', [], ['psn' => 'P-1'])->assertOk();

        $this->actingAs($this->operator)->getJson(route('packaging.stats'))
            ->assertOk()
            ->assertJsonPath('today_packed', 1)
            ->assertJsonPath('plan', 10)
            ->assertJsonPath('total_packed', 1)
            ->assertJsonPath('backlog', 9);
    }
}
