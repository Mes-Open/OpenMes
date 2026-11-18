<?php

namespace Tests\Feature\Logistics;

use App\Enums\PalletStatus;
use App\Enums\ShipmentStatus;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentScan;
use App\Models\UnitCarton;
use App\Models\User;
use App\Services\Logistics\ShipmentLoadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The gates that keep a unit from leaving twice or leaving when it must not:
 * quality of a carton's pallet, shipped cartons, a carton and its pallet on two
 * trucks (or counted twice on one), the list row's unit type, and the re-check
 * of every loaded unit when the load is confirmed.
 */
class ShipmentLoadingSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    private ShipmentLoadingService $service;

    private User $operator;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');

        $this->service = app(ShipmentLoadingService::class);
    }

    private function readyPallet(array $attributes = []): Pallet
    {
        return Pallet::factory()->closed()->create($attributes + ['quality_status' => Pallet::QUALITY_PASS]);
    }

    private function closedCarton(?Pallet $pallet = null, array $attributes = []): UnitCarton
    {
        return UnitCarton::factory()->closed()->create($attributes + ['pallet_id' => $pallet?->id]);
    }

    private function shipmentWith(Pallet|UnitCarton ...$units): Shipment
    {
        $shipment = Shipment::factory()->create();
        foreach ($units as $unit) {
            $factory = ShipmentItem::factory()->for($shipment);
            ($unit instanceof Pallet ? $factory->forPallet($unit) : $factory->forCarton($unit))->create();
        }

        return $shipment;
    }

    private function scanResult(Shipment $shipment, string $code): string
    {
        return $this->service->scan($shipment, $code, $this->operator)->result;
    }

    // ── 1. A carton is held to its pallet's quality verdict ────────────────

    public function test_a_carton_on_a_pallet_pending_quality_is_blocked(): void
    {
        $pallet = $this->readyPallet(['quality_status' => Pallet::QUALITY_PENDING]);
        $carton = $this->closedCarton($pallet);
        $shipment = $this->shipmentWith($carton);

        $scan = $this->service->scan($shipment, $carton->carton_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString($pallet->pallet_no, $scan->message);
        $this->assertNull($shipment->items()->first()->loaded_at);
    }

    public function test_a_carton_on_a_quality_failed_pallet_is_blocked(): void
    {
        $pallet = $this->readyPallet(['quality_status' => Pallet::QUALITY_FAIL]);
        $carton = $this->closedCarton($pallet);
        $shipment = $this->shipmentWith($carton);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $this->scanResult($shipment, $carton->carton_no));
    }

    // ── 2. A shipped carton stays shipped ──────────────────────────────────

    public function test_a_loose_carton_without_units_ships_once_and_is_blocked_afterwards(): void
    {
        $carton = $this->closedCarton();
        $first = $this->shipmentWith($carton);
        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->scanResult($first, $carton->carton_no));
        $this->service->confirm($first, $this->operator);

        $this->assertNotNull($carton->fresh()->shipped_at);

        $second = $this->shipmentWith($carton);
        $scan = $this->service->scan($second, $carton->carton_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString('already been shipped', $scan->message);
    }

    // ── 3. A carton and its pallet: one truck, counted once ────────────────

    public function test_a_pallet_whose_carton_is_loaded_on_another_shipment_is_blocked(): void
    {
        $pallet = $this->readyPallet();
        $carton = $this->closedCarton($pallet);
        $truckA = $this->shipmentWith($carton);
        $truckB = $this->shipmentWith($pallet);
        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->scanResult($truckA, $carton->carton_no));

        $scan = $this->service->scan($truckB, $pallet->pallet_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString($truckA->shipment_no, $scan->message);
    }

    public function test_a_carton_whose_pallet_is_loaded_on_another_shipment_is_blocked(): void
    {
        $pallet = $this->readyPallet();
        $carton = $this->closedCarton($pallet);
        $truckA = $this->shipmentWith($pallet);
        $truckB = $this->shipmentWith($carton);
        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->scanResult($truckA, $pallet->pallet_no));

        $scan = $this->service->scan($truckB, $carton->carton_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString($truckA->shipment_no, $scan->message);
    }

    public function test_a_pallet_after_its_carton_was_loaded_loose_on_the_same_shipment_is_not_counted_twice(): void
    {
        $pallet = $this->readyPallet();
        $carton = $this->closedCarton($pallet);
        $shipment = $this->shipmentWith($carton, $pallet);
        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->scanResult($shipment, $carton->carton_no));

        $scan = $this->service->scan($shipment, $pallet->pallet_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString($carton->carton_no, $scan->message);
        $this->assertSame(1, $this->service->summary($shipment)['loaded']);
    }

    public function test_a_listed_carton_after_its_pallet_was_loaded_on_the_same_shipment_is_not_counted_twice(): void
    {
        $pallet = $this->readyPallet();
        $carton = $this->closedCarton($pallet);
        $shipment = $this->shipmentWith($pallet, $carton);
        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->scanResult($shipment, $pallet->pallet_no));

        $scan = $this->service->scan($shipment, $carton->carton_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_DUPLICATE, $scan->result);
        $this->assertStringContainsString($pallet->pallet_no, $scan->message);
        $this->assertSame(1, $this->service->summary($shipment)['loaded']);
        $this->assertNull(ShipmentItem::where('carton_id', $carton->id)->value('loaded_at'));
    }

    // ── 4. Two docks, two shipments, one unit ──────────────────────────────

    public function test_two_docks_scanning_one_unit_onto_two_shipments_load_it_once(): void
    {
        $pallet = $this->readyPallet();
        $truckA = $this->shipmentWith($pallet);
        $truckB = $this->shipmentWith($pallet);
        // Each dock holds its own stale instance; the service locks the shipment,
        // then the unit, and re-reads, so the second dock sees the first's load.
        $dockA = Shipment::find($truckA->id);
        $dockB = Shipment::find($truckB->id);

        $results = [
            $this->service->scan($dockA, $pallet->pallet_no, $this->operator)->result,
            $this->service->scan($dockB, $pallet->pallet_no, $this->supervisor)->result,
        ];

        $this->assertSame([ShipmentScan::RESULT_LOADED, ShipmentScan::RESULT_BLOCKED], $results);
        $this->assertSame(1, ShipmentItem::where('pallet_id', $pallet->id)->whereNotNull('loaded_at')->count());
    }

    // ── 5. The list row's unit type must match what the code is ────────────

    public function test_a_carton_does_not_satisfy_a_pallet_row_with_its_number(): void
    {
        $carton = $this->closedCarton();
        $shipment = Shipment::factory()->create();
        ShipmentItem::factory()->for($shipment)->create(['unit_type' => ShipmentItem::TYPE_PALLET, 'identifier' => $carton->carton_no]);

        $scan = $this->service->scan($shipment, $carton->carton_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_UNEXPECTED, $scan->result);
        $this->assertSame(ShipmentItem::TYPE_CARTON, $scan->unit_type);
        $this->assertSame(0, $this->service->summary($shipment)['loaded']);
    }

    public function test_a_pallet_does_not_satisfy_a_carton_row_with_its_number(): void
    {
        $pallet = $this->readyPallet();
        $shipment = Shipment::factory()->create();
        ShipmentItem::factory()->for($shipment)->create(['unit_type' => ShipmentItem::TYPE_CARTON, 'identifier' => $pallet->pallet_no]);

        $this->assertSame(ShipmentScan::RESULT_UNEXPECTED, $this->scanResult($shipment, $pallet->pallet_no));
        $this->assertSame(0, $this->service->summary($shipment)['loaded']);
    }

    public function test_when_a_pallet_and_a_carton_share_a_number_the_row_of_the_matching_type_wins(): void
    {
        $pallet = $this->readyPallet(['pallet_no' => 'SHARED-1']);
        $carton = $this->closedCarton(null, ['carton_no' => 'SHARED-1']);
        $shipment = Shipment::factory()->create();
        ShipmentItem::factory()->for($shipment)->create(['unit_type' => ShipmentItem::TYPE_CARTON, 'identifier' => 'SHARED-1']);

        $scan = $this->service->scan($shipment, 'SHARED-1', $this->operator);

        $this->assertSame(ShipmentScan::RESULT_LOADED, $scan->result);
        $this->assertSame(ShipmentItem::TYPE_CARTON, $scan->unit_type);
        $this->assertSame($carton->id, $scan->unit_id);
        $item = $shipment->items()->first();
        $this->assertSame($carton->id, $item->carton_id);
        $this->assertNull($item->pallet_id);
        $this->assertSame(PalletStatus::Closed, $pallet->fresh()->status);
    }

    // ── 7. Confirm re-checks every loaded unit, all or nothing ─────────────

    /** @return array{0: Shipment, 1: Pallet, 2: Pallet} a shipment with two loaded pallets */
    private function twoLoadedPallets(): array
    {
        $good = $this->readyPallet();
        $other = $this->readyPallet();
        $shipment = $this->shipmentWith($good, $other);
        $this->scanResult($shipment, $good->pallet_no);
        $this->scanResult($shipment, $other->pallet_no);

        return [$shipment, $good, $other];
    }

    private function assertConfirmRefused(Shipment $shipment, string $naming): void
    {
        try {
            $this->service->confirm($shipment, $this->supervisor, 'reason');
            $this->fail('Confirm must refuse a load with a unit that may no longer ship.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($naming, implode(' ', $e->errors()['shipment'] ?? []));
        }
        $this->assertSame(ShipmentStatus::Loading, $shipment->fresh()->status);
    }

    public function test_confirm_refuses_a_pallet_reopened_after_loading_and_ships_nothing(): void
    {
        [$shipment, $good, $other] = $this->twoLoadedPallets();
        $other->update(['status' => PalletStatus::Open->value]);

        $this->assertConfirmRefused($shipment, $other->pallet_no);
        $this->assertSame(PalletStatus::Closed, $good->fresh()->status);
        $this->assertSame(PalletStatus::Open, $other->fresh()->status);
    }

    public function test_confirm_refuses_a_pallet_whose_quality_changed_after_loading(): void
    {
        [$shipment, $good, $other] = $this->twoLoadedPallets();
        $other->update(['quality_status' => Pallet::QUALITY_PENDING]);

        $this->assertConfirmRefused($shipment, $other->pallet_no);
        $this->assertSame(PalletStatus::Closed, $good->fresh()->status);
    }

    public function test_confirm_refuses_a_pallet_with_units_put_on_hold_after_loading(): void
    {
        [$shipment, $good, $other] = $this->twoLoadedPallets();
        $held = SerialUnit::factory()->create(['pallet_id' => $other->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        $held->update(['status' => SerialUnit::STATUS_BLOCKED]);

        $this->assertConfirmRefused($shipment, $other->pallet_no);
        $this->assertSame(PalletStatus::Closed, $good->fresh()->status);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $held->fresh()->status);
    }

    public function test_confirm_refuses_a_loose_carton_whose_pallet_failed_quality_after_loading(): void
    {
        $good = $this->readyPallet();
        $parent = $this->readyPallet();
        $carton = $this->closedCarton($parent);
        $unit = SerialUnit::factory()->create(['carton_id' => $carton->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        $shipment = $this->shipmentWith($good, $carton);
        $this->scanResult($shipment, $good->pallet_no);
        $this->scanResult($shipment, $carton->carton_no);
        $parent->update(['quality_status' => Pallet::QUALITY_FAIL]);

        $this->assertConfirmRefused($shipment, $carton->carton_no);
        $this->assertSame(PalletStatus::Closed, $good->fresh()->status);
        $this->assertSame(SerialUnit::STATUS_COMPLETED, $unit->fresh()->status);
        $this->assertNull($carton->fresh()->shipped_at);
    }

    public function test_confirm_refuses_a_pallet_shipped_elsewhere_in_the_meantime(): void
    {
        [$shipment, $good, $other] = $this->twoLoadedPallets();
        $other->update(['status' => PalletStatus::Shipped->value, 'shipped_at' => now()]);

        $this->assertConfirmRefused($shipment, $other->pallet_no);
        $this->assertSame(PalletStatus::Closed, $good->fresh()->status);
    }
}
