<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PalletStatus;
use App\Models\Line;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Tenant;
use App\Models\UnitCarton;
use App\Models\User;
use App\Models\Workstation;
use App\Services\Logistics\ShipmentLoadingService;
use App\Support\Integration\ShipmentPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** REST /api/v1/shipments (#400): plan, scan, undo, confirm, read the result. */
class ShipmentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $supervisor;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->tenant = Tenant::create(['name' => 'Plant A']);
        $this->operator = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->operator->assignRole('Operator');
        $this->supervisor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->supervisor->assignRole('Supervisor');
    }

    private function pallet(array $attributes = []): Pallet
    {
        return Pallet::factory()->closed()->create($attributes + ['quality_status' => Pallet::QUALITY_PASS, 'tenant_id' => $this->tenant->id]);
    }

    public function test_full_flow_create_scan_undo_confirm_and_result(): void
    {
        $a = $this->pallet();
        SerialUnit::factory()->count(3)->create(['pallet_id' => $a->id, 'status' => SerialUnit::STATUS_COMPLETED, 'tenant_id' => $this->tenant->id]);
        $b = $this->pallet();

        $id = $this->actingAs($this->supervisor)->postJson('/api/v1/shipments', [
            'shipment_no' => 'LOAD-2026/001',
            'carrier' => 'Fast Freight',
            'vehicle_no' => 'WX 12345',
            'destination' => 'Berlin DC',
            'items' => [
                ['unit_type' => 'pallet', 'identifier' => $a->pallet_no],
                ['identifier' => $b->pallet_no],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.shipment_no', 'LOAD-2026/001')
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.totals.expected', 2)
            ->json('data.id');

        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$id}/scan", ['code' => $a->pallet_no])
            ->assertOk()
            ->assertJsonPath('data.scan.result', 'loaded')
            ->assertJsonPath('data.totals.loaded', 1)
            ->assertJsonPath('data.totals.missing', 1);
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$id}/scan", ['code' => $b->pallet_no])
            ->assertJsonPath('data.scan.result', 'loaded');
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$id}/scan", ['code' => 'GHOST-1'])
            ->assertJsonPath('data.scan.result', 'unknown');

        // Undo one, then load it again.
        $itemB = ShipmentItem::where('identifier', $b->pallet_no)->first();
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$id}/items/{$itemB->id}/unload")
            ->assertOk()->assertJsonPath('data.scan.result', 'removed')->assertJsonPath('data.totals.loaded', 1);
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$id}/scan", ['code' => $b->pallet_no])
            ->assertJsonPath('data.totals.loaded', 2);

        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.confirmed_by', $this->operator->name)
            ->assertJsonPath('data.totals.loaded', 2)
            ->assertJsonPath('data.totals.missing', 0)
            ->assertJsonPath('data.totals.loaded_units', 3)
            ->assertJsonPath('data.loaded.0.identifier', $a->pallet_no)
            ->assertJsonPath('data.loaded.0.unit_count', 3)
            ->assertJsonPath('data.unexpected.0.scanned_code', 'GHOST-1');

        $this->assertSame(PalletStatus::Shipped, $a->fresh()->status);
        $this->actingAs($this->operator)->getJson("/api/v1/shipments/{$id}")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->actingAs($this->operator)->getJson('/api/v1/shipments')->assertOk()->assertJsonPath('data.0.shipment_no', 'LOAD-2026/001');

        // Closed for business.
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$id}/scan", ['code' => $a->pallet_no])->assertUnprocessable();
    }

    public function test_store_validates_numbers_and_the_list(): void
    {
        $this->actingAs($this->supervisor)->postJson('/api/v1/shipments', [
            'shipment_no' => 'LOAD 1; DROP',
            'vehicle_no' => '<script>',
            'items' => [['unit_type' => 'crate', 'identifier' => 'PAL 1']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['shipment_no', 'vehicle_no', 'items.0.unit_type', 'items.0.identifier']);

        $this->actingAs($this->supervisor)->postJson('/api/v1/shipments', ['shipment_no' => 'LOAD-1', 'items' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['items']);

        $this->actingAs($this->supervisor)->postJson('/api/v1/shipments', [
            'shipment_no' => str_repeat('A', 65), 'items' => [['identifier' => 'PAL-1']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['shipment_no']);
    }

    public function test_shipment_no_is_unique_among_live_shipments_and_reusable_after_delete(): void
    {
        $existing = Shipment::factory()->create(['shipment_no' => 'LOAD-7', 'tenant_id' => $this->tenant->id]);
        $payload = ['shipment_no' => 'LOAD-7', 'items' => [['identifier' => 'PAL-1']]];

        $this->actingAs($this->supervisor)->postJson('/api/v1/shipments', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['shipment_no']);

        $this->actingAs($this->supervisor);
        $existing->delete();
        $this->assertSoftDeleted('shipments', ['id' => $existing->id]);

        $this->postJson('/api/v1/shipments', $payload)->assertCreated();
    }

    public function test_scan_validates_the_code(): void
    {
        $shipment = Shipment::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$shipment->id}/scan", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$shipment->id}/scan", ['code' => 'PAL 0001 OR 1=1'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_short_load_needs_a_supervisor_and_a_reason(): void
    {
        $a = $this->pallet();
        $b = $this->pallet();
        $shipment = Shipment::factory()->create(['tenant_id' => $this->tenant->id]);
        ShipmentItem::factory()->for($shipment)->forPallet($a)->create();
        ShipmentItem::factory()->for($shipment)->forPallet($b)->create();
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$shipment->id}/scan", ['code' => $a->pallet_no])->assertOk();

        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$shipment->id}/confirm", ['short_reason' => 'Full'])
            ->assertForbidden();
        $this->actingAs($this->supervisor)->postJson("/api/v1/shipments/{$shipment->id}/confirm")
            ->assertUnprocessable()->assertJsonValidationErrors(['short_reason']);
        $this->actingAs($this->supervisor)->postJson("/api/v1/shipments/{$shipment->id}/confirm", ['short_reason' => 'Truck full'])
            ->assertOk()
            ->assertJsonPath('data.short_reason', 'Truck full')
            ->assertJsonPath('data.missing.0.identifier', $b->pallet_no);
    }

    public function test_guests_are_rejected(): void
    {
        $shipment = Shipment::factory()->create();

        $this->getJson('/api/v1/shipments')->assertUnauthorized();
        $this->postJson('/api/v1/shipments', [])->assertUnauthorized();
        $this->postJson("/api/v1/shipments/{$shipment->id}/scan", ['code' => 'X'])->assertUnauthorized();
        $this->postJson("/api/v1/shipments/{$shipment->id}/confirm")->assertUnauthorized();
    }

    public function test_operators_cannot_plan_shipments_and_users_without_a_dock_role_cannot_scan(): void
    {
        $this->actingAs($this->operator)->postJson('/api/v1/shipments', [
            'shipment_no' => 'LOAD-9', 'items' => [['identifier' => 'PAL-1']],
        ])->assertForbidden();

        $nobody = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $shipment = Shipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($nobody)->postJson("/api/v1/shipments/{$shipment->id}/scan", ['code' => 'PAL-1'])->assertForbidden();
        $this->actingAs($nobody)->getJson("/api/v1/shipments/{$shipment->id}")->assertForbidden();
        $this->actingAs($nobody)->getJson('/api/v1/shipments')->assertForbidden();
    }

    public function test_another_tenants_shipment_is_not_found(): void
    {
        $other = Tenant::create(['name' => 'Plant B']);
        $foreign = Shipment::factory()->create(['tenant_id' => $other->id]);
        $item = ShipmentItem::factory()->for($foreign)->create(['tenant_id' => $other->id]);

        $this->actingAs($this->operator)->getJson("/api/v1/shipments/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$foreign->id}/scan", ['code' => 'PAL-1'])->assertNotFound();
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$foreign->id}/items/{$item->id}/unload")->assertNotFound();
        $this->actingAs($this->operator)->getJson('/api/v1/shipments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_unit_of_another_tenant_is_unknown_to_the_scan(): void
    {
        $other = Tenant::create(['name' => 'Plant B']);
        $foreignPallet = Pallet::factory()->closed()->create(['quality_status' => Pallet::QUALITY_PASS, 'tenant_id' => $other->id]);
        $shipment = Shipment::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$shipment->id}/scan", ['code' => $foreignPallet->pallet_no])
            ->assertOk()->assertJsonPath('data.scan.result', 'unknown');
    }

    public function test_unload_refuses_an_item_of_another_shipment(): void
    {
        $one = Shipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $two = Shipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = ShipmentItem::factory()->for($two)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$one->id}/items/{$item->id}/unload")->assertNotFound();
    }

    public function test_a_workstation_of_another_tenant_is_rejected_on_scan_and_unload(): void
    {
        $other = Tenant::create(['name' => 'Plant B']);
        $foreignStation = Workstation::factory()->create(['line_id' => Line::factory()->create(['tenant_id' => $other->id])->id]);
        $ownStation = Workstation::factory()->create(['line_id' => Line::factory()->create(['tenant_id' => $this->tenant->id])->id]);
        $pallet = $this->pallet();
        $shipment = Shipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = ShipmentItem::factory()->for($shipment)->forPallet($pallet)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->operator)
            ->postJson("/api/v1/shipments/{$shipment->id}/scan", ['code' => $pallet->pallet_no, 'workstation_id' => $foreignStation->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['workstation_id']);
        $this->actingAs($this->operator)
            ->postJson("/api/v1/shipments/{$shipment->id}/scan", ['code' => $pallet->pallet_no, 'workstation_id' => $ownStation->id])
            ->assertOk()->assertJsonPath('data.scan.result', 'loaded');
        $this->actingAs($this->operator)
            ->postJson("/api/v1/shipments/{$shipment->id}/items/{$item->id}/unload", ['workstation_id' => $foreignStation->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['workstation_id']);
    }

    /** A shipment with $n loaded pallets (units on each) and $n loaded loose cartons. */
    private function loadedShipment(int $n): Shipment
    {
        $service = app(ShipmentLoadingService::class);
        $shipment = Shipment::factory()->create(['tenant_id' => $this->tenant->id]);
        for ($i = 0; $i < $n; $i++) {
            $pallet = $this->pallet();
            SerialUnit::factory()->count(2)->create(['pallet_id' => $pallet->id, 'status' => SerialUnit::STATUS_COMPLETED, 'tenant_id' => $this->tenant->id]);
            $carton = UnitCarton::factory()->closed()->create(['qty' => 4, 'tenant_id' => $this->tenant->id]);
            ShipmentItem::factory()->for($shipment)->forPallet($pallet)->create(['tenant_id' => $this->tenant->id]);
            ShipmentItem::factory()->for($shipment)->forCarton($carton)->create(['tenant_id' => $this->tenant->id]);
            $service->scan($shipment, $pallet->pallet_no, $this->operator);
            $service->scan($shipment, $carton->carton_no, $this->operator);
        }
        $service->scan($shipment, 'STRAY-1', $this->operator);

        return $shipment;
    }

    private function queriesFor(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_presenting_a_shipment_takes_a_bounded_number_of_queries(): void
    {
        $small = $this->loadedShipment(2);
        $large = $this->loadedShipment(25);

        $smallCount = $this->queriesFor(fn () => ShipmentPresenter::present(Shipment::find($small->id)));
        $payload = null;
        $largeCount = $this->queriesFor(function () use ($large, &$payload) {
            $payload = ShipmentPresenter::present(Shipment::find($large->id));
        });

        $this->assertSame($smallCount, $largeCount, 'Query count must not grow with the number of items.');
        $this->assertLessThanOrEqual(10, $largeCount);
        $this->assertSame(50, $payload['totals']['loaded']);
        $this->assertSame(25 * 2 + 25 * 4, $payload['totals']['loaded_units']);
        $this->assertSame(2, collect($payload['loaded'])->firstWhere('unit_type', 'pallet')['unit_count']);
        $this->assertSame(4, collect($payload['loaded'])->firstWhere('unit_type', 'carton')['unit_count']);
    }

    public function test_the_scan_response_does_not_load_the_whole_shipment(): void
    {
        $small = $this->loadedShipment(2);
        $large = $this->loadedShipment(25);
        $this->actingAs($this->operator);
        // Warm up the request pipeline (permission cache, settings) once.
        $this->postJson("/api/v1/shipments/{$small->id}/scan", ['code' => 'STRAY-0'])->assertOk();

        $smallCount = $this->queriesFor(fn () => $this->postJson("/api/v1/shipments/{$small->id}/scan", ['code' => 'STRAY-2'])->assertOk());
        $response = null;
        $largeCount = $this->queriesFor(function () use ($large, &$response) {
            $response = $this->postJson("/api/v1/shipments/{$large->id}/scan", ['code' => 'STRAY-2'])->assertOk();
        });

        $this->assertSame($smallCount, $largeCount, 'Query count must not grow with the number of items.');
        // The answer needs three counts, not the scan log or every row's unit.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson("/api/v1/shipments/{$large->id}/scan", ['code' => 'STRAY-3'])->assertOk();
        $reads = collect(DB::getQueryLog())->filter(fn (array $q) => str_starts_with(strtolower($q['query']), 'select'));
        DB::disableQueryLog();
        $this->assertEmpty($reads->filter(fn (array $q) => str_contains($q['query'], 'from "shipment_scans"'))->all(), 'The scan answer must not read the scan log.');
        $this->assertLessThan(10, $reads->max(fn (array $q) => count($q['bindings'])), 'The scan answer must not load every listed unit.');
        $response->assertJsonPath('data.totals.expected', 50)
            ->assertJsonPath('data.totals.loaded', 50)
            ->assertJsonPath('data.totals.missing', 0);
    }
}
