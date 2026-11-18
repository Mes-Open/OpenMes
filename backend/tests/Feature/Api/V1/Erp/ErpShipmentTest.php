<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Pallet;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Logistics\ShipmentLoadingService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * ERP shipments (#400): loads in under erp:shipments:write, load results out
 * under erp:shipments:read, by number and as a `?since=` feed.
 */
class ErpShipmentTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'ERP Tenant'])->id;
        app(TenantContext::class)->set($this->tenantId);
    }

    private function keyWith(array $scopes, ?int $tenantId = null): string
    {
        [, $plaintext] = ApiKey::issue([
            'name' => 'ERP shipments key',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $tenantId ?? $this->tenantId,
        ]);

        return $plaintext;
    }

    private function pallet(): Pallet
    {
        return Pallet::factory()->closed()->create(['quality_status' => Pallet::QUALITY_PASS, 'tenant_id' => $this->tenantId]);
    }

    public function test_erp_sends_a_load_and_reads_the_result_back(): void
    {
        $a = $this->pallet();
        $b = $this->pallet();
        $write = $this->keyWith([ApiScope::ShipmentsWrite]);
        $read = $this->keyWith([ApiScope::ShipmentsRead]);

        $this->withHeader('X-Api-Key', $write)->postJson('/api/v1/erp/shipments', [
            'shipment_no' => 'SO-4711/1',
            'erp_reference' => 'SO-4711',
            'destination' => 'Customer DC',
            'items' => [['identifier' => $a->pallet_no], ['unit_type' => 'pallet', 'identifier' => $b->pallet_no]],
        ])->assertCreated()->assertJsonPath('data.totals.expected', 2);

        $shipment = Shipment::where('shipment_no', 'SO-4711/1')->firstOrFail();
        $this->assertSame($this->tenantId, $shipment->tenant_id);

        // The dock loads one pallet, scans a stray code, a supervisor ships it short.
        Role::findOrCreate('Supervisor', 'web');
        $supervisor = User::factory()->create(['tenant_id' => $this->tenantId]);
        $supervisor->assignRole('Supervisor');
        $service = app(ShipmentLoadingService::class);
        $service->scan($shipment, $a->pallet_no, $supervisor);
        $service->scan($shipment, 'STRAY-9', $supervisor);
        $service->confirm($shipment, $supervisor, 'Second pallet not ready');

        $this->withHeader('X-Api-Key', $read)->getJson('/api/v1/erp/shipments/'.rawurlencode('SO-4711/1'))
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.confirmed_by', $supervisor->name)
            ->assertJsonPath('data.short_reason', 'Second pallet not ready')
            ->assertJsonPath('data.loaded.0.identifier', $a->pallet_no)
            ->assertJsonPath('data.missing.0.identifier', $b->pallet_no)
            ->assertJsonPath('data.unexpected.0.scanned_code', 'STRAY-9');

        $this->withHeader('X-Api-Key', $read)->getJson('/api/v1/erp/shipments?status=confirmed&since='.urlencode(now()->subHour()->toIso8601String()))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shipment_no', 'SO-4711/1')
            ->assertJsonPath('meta.has_more', false);
        $this->withHeader('X-Api-Key', $read)->getJson('/api/v1/erp/shipments?since='.urlencode(now()->addHour()->toIso8601String()))
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_resending_a_planned_load_replaces_it_and_a_started_one_is_409(): void
    {
        $key = $this->keyWith([ApiScope::ShipmentsWrite]);
        $payload = ['shipment_no' => 'SO-1', 'items' => [['identifier' => 'PAL-A'], ['identifier' => 'PAL-B']]];

        $this->withHeader('X-Api-Key', $key)->postJson('/api/v1/erp/shipments', $payload)->assertCreated();
        $payload['items'] = [['identifier' => 'PAL-B'], ['identifier' => 'PAL-C']];
        $this->withHeader('X-Api-Key', $key)->postJson('/api/v1/erp/shipments', $payload)
            ->assertOk()->assertJsonPath('data.totals.expected', 2);

        $shipment = Shipment::where('shipment_no', 'SO-1')->first();
        $this->assertSame(['PAL-B', 'PAL-C'], $shipment->items()->orderBy('identifier')->pluck('identifier')->all());
        $this->assertSoftDeleted('shipment_items', ['shipment_id' => $shipment->id, 'identifier' => 'PAL-A']);

        $shipment->update(['status' => 'loading']);
        $this->withHeader('X-Api-Key', $key)->postJson('/api/v1/erp/shipments', $payload)->assertStatus(409);
    }

    public function test_store_validates_the_payload(): void
    {
        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ShipmentsWrite]))
            ->postJson('/api/v1/erp/shipments', [
                'shipment_no' => 'bad no',
                'items' => [['identifier' => 'PAL;1'], ['unit_type' => 'drum', 'identifier' => 'X']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipment_no', 'items.0.identifier', 'items.1.unit_type']);

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ShipmentsRead]))
            ->getJson('/api/v1/erp/shipments?since=not-a-date')
            ->assertUnprocessable()->assertJsonValidationErrors(['since']);
    }

    public function test_requests_without_a_key_are_401(): void
    {
        $this->postJson('/api/v1/erp/shipments', [])->assertUnauthorized();
        $this->getJson('/api/v1/erp/shipments')->assertUnauthorized();
        $this->getJson('/api/v1/erp/shipments/SO-1')->assertUnauthorized();
    }

    public function test_a_key_without_the_scope_is_403(): void
    {
        $readOnly = $this->keyWith([ApiScope::ShipmentsRead]);
        $writeOnly = $this->keyWith([ApiScope::ShipmentsWrite]);

        $this->withHeader('X-Api-Key', $readOnly)
            ->postJson('/api/v1/erp/shipments', ['shipment_no' => 'SO-2', 'items' => [['identifier' => 'PAL-1']]])
            ->assertForbidden();
        $this->withHeader('X-Api-Key', $writeOnly)->getJson('/api/v1/erp/shipments')->assertForbidden();
        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::StockRead]))->getJson('/api/v1/erp/shipments/SO-2')->assertForbidden();
    }

    public function test_a_key_sees_only_its_own_tenants_shipments(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other'])->id;
        Shipment::factory()->create(['shipment_no' => 'FOREIGN-1', 'tenant_id' => $otherTenant]);
        Shipment::factory()->create(['shipment_no' => 'MINE-1', 'tenant_id' => $this->tenantId]);
        $key = $this->keyWith([ApiScope::ShipmentsRead]);

        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/erp/shipments/FOREIGN-1')->assertNotFound();
        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/erp/shipments')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.shipment_no', 'MINE-1');
    }
}
