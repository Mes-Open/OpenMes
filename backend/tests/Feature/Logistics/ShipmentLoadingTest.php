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
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Logistics\ShipmentLoadingException;
use App\Services\Logistics\ShipmentLoadingService;
use App\Support\WebhookEventRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Scan-to-load verification (#400): every answer the dock can get for a scan,
 * the undo, and what confirming a full / short load does to pallets and units.
 */
class ShipmentLoadingTest extends TestCase
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

    /** A closed, quality-released pallet — the normal thing to load. */
    private function readyPallet(array $attributes = []): Pallet
    {
        return Pallet::factory()->closed()->create($attributes + ['quality_status' => Pallet::QUALITY_PASS]);
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

    // ── Scan results ────────────────────────────────────────────────────────

    public function test_a_listed_pallet_is_loaded_and_the_first_scan_starts_loading(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);

        $scan = $this->service->scan($shipment, $pallet->pallet_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_LOADED, $scan->result);
        $item = $shipment->items()->first();
        $this->assertNotNull($item->loaded_at);
        $this->assertSame($this->operator->id, $item->loaded_by_id);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Loading, $shipment->status);
        $this->assertSame($this->operator->id, $shipment->started_by_id);
        $this->assertNotNull($shipment->started_at);
    }

    public function test_scanner_noise_around_the_code_is_ignored(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);

        $scan = $this->service->scan($shipment, "  {$pallet->pallet_no}\r\n", $this->operator);

        $this->assertSame(ShipmentScan::RESULT_LOADED, $scan->result);
        $this->assertSame($pallet->pallet_no, $scan->scanned_code);
    }

    public function test_scanning_a_loaded_pallet_again_is_a_duplicate_and_counts_once(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);

        $first = $this->service->scan($shipment, $pallet->pallet_no, $this->operator);
        $second = $this->service->scan($shipment, $pallet->pallet_no, $this->supervisor);

        $this->assertSame(ShipmentScan::RESULT_LOADED, $first->result);
        $this->assertSame(ShipmentScan::RESULT_DUPLICATE, $second->result);
        $summary = $this->service->summary($shipment);
        $this->assertSame(1, $summary['loaded']);
        $this->assertCount(1, $summary['duplicates']);
        // Loaded by the first scanner, not overwritten by the second.
        $this->assertSame($this->operator->id, $shipment->items()->first()->loaded_by_id);
    }

    public function test_two_scanners_scanning_the_same_unit_load_it_once(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);
        // Two terminals hold the same stale Shipment instance; the service locks
        // and re-reads the row, so the second scan sees the first one's write.
        $terminalA = Shipment::find($shipment->id);
        $terminalB = Shipment::find($shipment->id);

        $results = [
            $this->service->scan($terminalA, $pallet->pallet_no, $this->operator)->result,
            $this->service->scan($terminalB, $pallet->pallet_no, $this->supervisor)->result,
        ];

        sort($results);
        $this->assertSame([ShipmentScan::RESULT_DUPLICATE, ShipmentScan::RESULT_LOADED], $results);
        $this->assertSame(1, ShipmentItem::whereNotNull('loaded_at')->count());
        $this->assertSame(2, ShipmentScan::where('shipment_id', $shipment->id)->count());
    }

    public function test_a_real_pallet_not_on_the_list_is_unexpected(): void
    {
        $shipment = $this->shipmentWith($this->readyPallet());
        $stray = $this->readyPallet();

        $scan = $this->service->scan($shipment, $stray->pallet_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_UNEXPECTED, $scan->result);
        $this->assertSame(ShipmentItem::TYPE_PALLET, $scan->unit_type);
        $this->assertSame($stray->id, $scan->unit_id);
        $this->assertSame(0, $this->service->summary($shipment)['loaded']);
    }

    public function test_an_unknown_code_is_unknown(): void
    {
        $shipment = $this->shipmentWith($this->readyPallet());

        $scan = $this->service->scan($shipment, 'NOPE-123', $this->operator);

        $this->assertSame(ShipmentScan::RESULT_UNKNOWN, $scan->result);
        $this->assertNull($scan->unit_id);
        $this->assertCount(1, $this->service->summary($shipment)['unexpected']);
    }

    public function test_a_quality_failed_pallet_is_blocked(): void
    {
        $pallet = $this->readyPallet(['quality_status' => Pallet::QUALITY_FAIL]);
        $shipment = $this->shipmentWith($pallet);

        $scan = $this->service->scan($shipment, $pallet->pallet_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString('quality', $scan->message);
        $this->assertNull($shipment->items()->first()->loaded_at);
    }

    public function test_an_open_pallet_is_blocked(): void
    {
        $pallet = Pallet::factory()->create(['status' => PalletStatus::Open->value, 'quality_status' => Pallet::QUALITY_PASS]);
        $shipment = $this->shipmentWith($pallet);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $this->service->scan($shipment, $pallet->pallet_no, $this->operator)->result);
    }

    public function test_a_pallet_with_held_units_is_blocked(): void
    {
        $pallet = $this->readyPallet();
        SerialUnit::factory()->create(['pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_BLOCKED]);
        $shipment = $this->shipmentWith($pallet);

        $scan = $this->service->scan($shipment, $pallet->pallet_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString('held', $scan->message);
    }

    public function test_a_unit_loaded_on_another_active_shipment_is_blocked(): void
    {
        $pallet = $this->readyPallet();
        $first = $this->shipmentWith($pallet);
        $second = Shipment::factory()->create(['shipment_no' => 'LOAD-SECOND']);
        ShipmentItem::factory()->for($second)->forPallet($pallet)->create();

        $this->service->scan($first, $pallet->pallet_no, $this->operator);
        $scan = $this->service->scan($second, $pallet->pallet_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString($first->shipment_no, $scan->message);
    }

    public function test_a_listed_loose_carton_is_loaded(): void
    {
        $carton = UnitCarton::factory()->closed()->create();
        $shipment = $this->shipmentWith($carton);

        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->service->scan($shipment, $carton->carton_no, $this->operator)->result);
    }

    public function test_a_carton_on_a_listed_pallet_is_informational_and_not_counted_twice(): void
    {
        $pallet = $this->readyPallet();
        $carton = UnitCarton::factory()->closed()->create(['pallet_id' => $pallet->id]);
        $shipment = $this->shipmentWith($pallet);

        $this->service->scan($shipment, $pallet->pallet_no, $this->operator);
        $scan = $this->service->scan($shipment, $carton->carton_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_DUPLICATE, $scan->result);
        $this->assertStringContainsString($pallet->pallet_no, $scan->message);
        $summary = $this->service->summary($shipment);
        $this->assertSame(1, $summary['expected']);
        $this->assertSame(1, $summary['loaded']);
        $this->assertCount(0, $summary['unexpected']);
    }

    public function test_every_scan_is_logged(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);

        foreach ([$pallet->pallet_no, $pallet->pallet_no, 'X-1'] as $code) {
            $this->service->scan($shipment, $code, $this->operator, null);
        }

        $this->assertSame(
            [ShipmentScan::RESULT_LOADED, ShipmentScan::RESULT_DUPLICATE, ShipmentScan::RESULT_UNKNOWN],
            $shipment->scans()->orderBy('id')->pluck('result')->all(),
        );
    }

    // ── Undo ───────────────────────────────────────────────────────────────

    public function test_unload_takes_a_unit_off_and_keeps_the_history(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);
        $this->service->scan($shipment, $pallet->pallet_no, $this->operator);
        $item = $shipment->items()->first();

        $removed = $this->service->unload($item, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_REMOVED, $removed->result);
        $this->assertNull($item->fresh()->loaded_at);
        $this->assertSame([ShipmentScan::RESULT_LOADED, ShipmentScan::RESULT_REMOVED], $shipment->scans()->orderBy('id')->pluck('result')->all());

        // It can be loaded again.
        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->service->scan($shipment, $pallet->pallet_no, $this->operator)->result);
    }

    public function test_an_item_that_is_not_loaded_cannot_be_unloaded(): void
    {
        $shipment = $this->shipmentWith($this->readyPallet());

        $this->expectException(ShipmentLoadingException::class);
        $this->service->unload($shipment->items()->first(), $this->operator);
    }

    // ── Confirm ────────────────────────────────────────────────────────────

    public function test_confirming_a_full_load_ships_pallets_their_units_and_loose_cartons(): void
    {
        Queue::fake();
        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::SHIPMENT_CONFIRMED])->create();
        $pallet = $this->readyPallet();
        $palletUnit = SerialUnit::factory()->create(['pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        $carton = UnitCarton::factory()->closed()->create();
        $cartonUnit = SerialUnit::factory()->create(['carton_id' => $carton->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        $shipment = $this->shipmentWith($pallet, $carton);

        $this->service->scan($shipment, $pallet->pallet_no, $this->operator);
        $this->service->scan($shipment, $carton->carton_no, $this->operator);
        $confirmed = $this->service->confirm($shipment, $this->operator);

        $this->assertSame(ShipmentStatus::Confirmed, $confirmed->status);
        $this->assertSame($this->operator->id, $confirmed->confirmed_by_id);
        $this->assertNull($confirmed->short_reason);

        $pallet->refresh();
        $this->assertSame(PalletStatus::Shipped, $pallet->status);
        $this->assertNotNull($pallet->shipped_at);
        $this->assertSame(SerialUnit::STATUS_SHIPPED, $palletUnit->fresh()->status);
        $this->assertSame(SerialUnit::STATUS_SHIPPED, $cartonUnit->fresh()->status);
        $this->assertNotNull($cartonUnit->fresh()->shipped_at);

        $delivery = WebhookDelivery::where('webhook_id', $webhook->id)->where('event_type', WebhookEventRegistry::SHIPMENT_CONFIRMED)->first();
        $this->assertNotNull($delivery);
        $this->assertSame($shipment->shipment_no, $delivery->payload['data']['shipment_no']);
        $this->assertSame(2, $delivery->payload['data']['totals']['loaded']);
    }

    public function test_a_unit_held_after_loading_stops_the_confirm_and_keeps_its_hold(): void
    {
        $pallet = $this->readyPallet();
        $good = SerialUnit::factory()->create(['pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        $held = SerialUnit::factory()->create(['pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        $shipment = $this->shipmentWith($pallet);
        $this->service->scan($shipment, $pallet->pallet_no, $this->operator);

        // Put on hold after it was loaded (a late test result): the pallet may
        // not leave with it. Undo, take the unit off, scan again.
        $held->update(['status' => SerialUnit::STATUS_BLOCKED]);
        try {
            $this->service->confirm($shipment, $this->operator);
            $this->fail('A pallet with a held unit must not ship.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($pallet->pallet_no, $e->errors()['shipment'][0]);
        }

        $this->assertSame(PalletStatus::Closed, $pallet->fresh()->status);
        $this->assertSame(SerialUnit::STATUS_COMPLETED, $good->fresh()->status);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $held->fresh()->status);
        $this->assertNull($held->fresh()->shipped_at);
    }

    public function test_a_short_load_without_a_reason_is_refused(): void
    {
        $loaded = $this->readyPallet();
        $shipment = $this->shipmentWith($loaded, $this->readyPallet());
        $this->service->scan($shipment, $loaded->pallet_no, $this->supervisor);

        try {
            $this->service->confirm($shipment, $this->supervisor, '  ');
            $this->fail('A short load must need a reason.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('short_reason', $e->errors());
        }
        $this->assertSame(ShipmentStatus::Loading, $shipment->fresh()->status);
        $this->assertSame(PalletStatus::Closed, $loaded->fresh()->status);
    }

    public function test_an_operator_cannot_confirm_a_short_load(): void
    {
        $loaded = $this->readyPallet();
        $shipment = $this->shipmentWith($loaded, $this->readyPallet());
        $this->service->scan($shipment, $loaded->pallet_no, $this->operator);

        $this->expectException(AuthorizationException::class);
        $this->service->confirm($shipment, $this->operator, 'Truck full');
    }

    public function test_a_supervisor_confirms_a_short_load_with_a_reason(): void
    {
        $loaded = $this->readyPallet();
        $missing = $this->readyPallet();
        $shipment = $this->shipmentWith($loaded, $missing);
        $this->service->scan($shipment, $loaded->pallet_no, $this->operator);

        $confirmed = $this->service->confirm($shipment, $this->supervisor, 'Pallet damaged, follows tomorrow');

        $this->assertSame(ShipmentStatus::Confirmed, $confirmed->status);
        $this->assertSame('Pallet damaged, follows tomorrow', $confirmed->short_reason);
        $this->assertSame(PalletStatus::Shipped, $loaded->fresh()->status);
        $this->assertSame(PalletStatus::Closed, $missing->fresh()->status);
    }

    public function test_nothing_loaded_cannot_be_confirmed(): void
    {
        $shipment = $this->shipmentWith($this->readyPallet());

        $this->expectException(ValidationException::class);
        $this->service->confirm($shipment, $this->supervisor, 'whatever');
    }

    public function test_a_confirmed_shipment_rejects_further_scans_and_undo(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);
        $this->service->scan($shipment, $pallet->pallet_no, $this->operator);
        $this->service->confirm($shipment, $this->operator);

        try {
            $this->service->scan($shipment, $pallet->pallet_no, $this->operator);
            $this->fail('A confirmed shipment must reject scans.');
        } catch (ShipmentLoadingException) {
        }

        $this->expectException(ShipmentLoadingException::class);
        $this->service->unload($shipment->items()->first(), $this->operator);
    }

    public function test_a_cancelled_shipment_rejects_scans(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);
        $this->service->cancel($shipment);

        $this->expectException(ShipmentLoadingException::class);
        $this->service->scan($shipment, $pallet->pallet_no, $this->operator);
    }

    // ── Expected list ──────────────────────────────────────────────────────

    public function test_create_resolves_and_detects_unit_types(): void
    {
        $pallet = $this->readyPallet();
        $carton = UnitCarton::factory()->closed()->create();

        $shipment = $this->service->create(['shipment_no' => 'LOAD-1'], [
            ['identifier' => $pallet->pallet_no],
            ['identifier' => $carton->carton_no],
            ['unit_type' => 'carton', 'identifier' => 'EXT-77'],
            ['identifier' => $pallet->pallet_no], // listed twice — kept once
        ], $this->supervisor);

        $items = $shipment->items()->orderBy('id')->get();
        $this->assertCount(3, $items);
        $this->assertSame(['pallet', 'carton', 'carton'], $items->pluck('unit_type')->all());
        $this->assertSame($pallet->id, $items[0]->pallet_id);
        $this->assertSame($carton->id, $items[1]->carton_id);
        $this->assertNull($items[2]->carton_id);
        $this->assertSame($this->supervisor->id, $shipment->created_by_id);
    }

    public function test_a_loaded_unit_cannot_be_dropped_from_the_list(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);
        $this->service->scan($shipment, $pallet->pallet_no, $this->operator);

        $this->expectException(ShipmentLoadingException::class);
        $this->service->update($shipment, [], [['unit_type' => 'pallet', 'identifier' => 'PAL-OTHER']]);
    }

    public function test_deleting_a_shipment_soft_deletes_its_items_and_scans(): void
    {
        $pallet = $this->readyPallet();
        $shipment = $this->shipmentWith($pallet);
        $this->service->scan($shipment, 'X-1', $this->operator);
        $this->actingAs($this->supervisor);

        $shipment->delete();

        $this->assertSoftDeleted('shipments', ['id' => $shipment->id, 'deleted_by_id' => $this->supervisor->id]);
        $this->assertSoftDeleted('shipment_items', ['shipment_id' => $shipment->id]);
        $this->assertSoftDeleted('shipment_scans', ['shipment_id' => $shipment->id]);
    }
}
