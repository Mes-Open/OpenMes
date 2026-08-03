<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Material;
use App\Models\ProductType;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ERP ↔ OpenMES warehouse sync (#212): balance snapshots in, balances and the
 * stock-document backlog out, acknowledgement to close the loop.
 */
class ErpStockSyncTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'ERP Tenant'])->id;
        app(TenantContext::class)->set($this->tenantId);
    }

    private function keyWith(array $scopes): string
    {
        [, $plaintext] = ApiKey::issue([
            'name' => 'ERP stock key',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $this->tenantId,
        ]);

        return $plaintext;
    }

    public function test_stock_import_replaces_balances_and_books_the_difference(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);
        $material = Material::factory()->create(['code' => 'FLOUR-01', 'stock_quantity' => 100]);

        $response = $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::StockWrite]))
            ->postJson('/api/v1/erp/stock/import', [
                'warehouse_code' => 'RAW-1',
                'balances' => [['material_code' => 'FLOUR-01', 'quantity' => 340.25, 'unit_of_measure' => 'kg']],
            ]);

        $response->assertOk()->assertJsonPath('data.imported', 1);

        $this->assertEquals(340.25, (float) WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('material_id', $material->id)
            ->value('quantity'));

        // The global quantity is re-derived, and the jump is audited rather than silent.
        $this->assertEquals(340.25, (float) $material->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $material->id,
            'movement_type' => StockMovement::TYPE_ADJUSTMENT,
            'source_type' => StockMovement::SOURCE_ERP_SYNC,
        ]);
        $this->assertNotNull($material->fresh()->last_stock_sync_at);
    }

    public function test_stock_import_is_idempotent(): void
    {
        Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);
        $material = Material::factory()->create(['code' => 'FLOUR-01']);
        $key = $this->keyWith([ApiScope::StockWrite]);

        $payload = [
            'warehouse_code' => 'RAW-1',
            'balances' => [['material_code' => 'FLOUR-01', 'quantity' => 50]],
        ];

        $this->withHeader('X-Api-Key', $key)->postJson('/api/v1/erp/stock/import', $payload)->assertOk();
        $this->withHeader('X-Api-Key', $key)->postJson('/api/v1/erp/stock/import', $payload)
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertEquals(50, (float) $material->fresh()->stock_quantity);
        // Only the first sync moved anything, so only it is in the ledger.
        $this->assertSame(1, StockMovement::count());
        $this->assertSame(1, WarehouseStock::count());
    }

    public function test_stock_import_rejects_rows_naming_both_or_neither_item(): void
    {
        Warehouse::factory()->create(['code' => 'MIX-1']);
        Material::factory()->create(['code' => 'FLOUR-01']);
        ProductType::factory()->create(['code' => 'BREAD-01']);

        $response = $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::StockWrite]))
            ->postJson('/api/v1/erp/stock/import', [
                'warehouse_code' => 'MIX-1',
                'balances' => [
                    ['material_code' => 'FLOUR-01', 'product_type_code' => 'BREAD-01', 'quantity' => 1],
                    ['quantity' => 1],
                    ['product_type_code' => 'BREAD-01', 'quantity' => 7],
                ],
            ]);

        $response->assertStatus(207)
            ->assertJsonPath('data.imported', 1)
            ->assertJsonCount(2, 'data.errors');
    }

    public function test_stock_import_refuses_a_warehouse_that_cannot_hold_the_item(): void
    {
        Warehouse::factory()->finishedGoods()->create(['code' => 'FG-1']);
        Material::factory()->create(['code' => 'FLOUR-01']);

        $response = $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::StockWrite]))
            ->postJson('/api/v1/erp/stock/import', [
                'balances' => [['warehouse_code' => 'FG-1', 'material_code' => 'FLOUR-01', 'quantity' => 5]],
            ]);

        $response->assertStatus(207)->assertJsonPath('data.errors.0.field', 'warehouse_code');
        $this->assertSame(0, WarehouseStock::count());
    }

    public function test_stock_import_resolves_a_warehouse_by_its_erp_code(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1', 'erp_code' => '0100']);
        Material::factory()->create(['code' => 'FLOUR-01']);

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::StockWrite]))
            ->postJson('/api/v1/erp/stock/import', [
                'balances' => [['warehouse_code' => '0100', 'material_code' => 'FLOUR-01', 'quantity' => 12]],
            ])->assertOk();

        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $warehouse->id, 'quantity' => 12]);
    }

    public function test_stock_export_lists_balances_with_codes(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1', 'erp_code' => '0100']);
        $material = Material::factory()->create(['code' => 'FLOUR-01']);

        WarehouseStock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'quantity' => 42.5,
        ]);

        $response = $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::StockRead]))
            ->getJson('/api/v1/erp/stock?warehouse=0100');

        $response->assertOk()
            ->assertJsonPath('data.0.warehouse_code', 'RAW-1')
            ->assertJsonPath('data.0.material_code', 'FLOUR-01')
            ->assertJsonPath('data.0.quantity', 42.5)
            ->assertJsonStructure(['meta' => ['next_cursor', 'has_more', 'count', 'per_page']]);
    }

    public function test_stock_document_export_defaults_to_posted_documents_with_lines(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1']);
        $material = Material::factory()->create(['code' => 'FLOUR-01']);

        $posted = StockDocument::factory()->posted()->create([
            'warehouse_id' => $warehouse->id,
            'document_no' => 'MI/2026/0001',
        ]);
        $posted->lines()->create(['material_id' => $material->id, 'quantity' => 15, 'unit_of_measure' => 'kg']);

        // A draft is not a real movement yet — booking it in the ERP would be premature.
        StockDocument::factory()->create(['warehouse_id' => $warehouse->id, 'document_no' => 'MI/2026/0002']);

        $response = $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::StockRead]))
            ->getJson('/api/v1/erp/stock-documents?unsynced_only=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document_no', 'MI/2026/0001')
            ->assertJsonPath('data.0.direction', 'out')
            ->assertJsonPath('data.0.warehouse_code', 'RAW-1')
            ->assertJsonPath('data.0.lines.0.material_code', 'FLOUR-01')
            // A whole float serialises to a JSON integer literal.
            ->assertJsonPath('data.0.lines.0.quantity', 15);
    }

    public function test_acknowledging_a_document_takes_it_off_the_backlog(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        $document = StockDocument::factory()->posted()->create(['warehouse_id' => $warehouse->id]);

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::StockWrite]))
            ->postJson("/api/v1/erp/stock-documents/{$document->id}/ack", ['erp_reference' => 'RW-2026/17'])
            ->assertOk()
            ->assertJsonPath('data.erp_reference', 'RW-2026/17');

        $this->assertNotNull($document->fresh()->erp_synced_at);

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::StockRead]))
            ->getJson('/api/v1/erp/stock-documents?unsynced_only=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_stock_endpoints_enforce_their_scopes(): void
    {
        $readOnly = $this->keyWith([ApiScope::StockRead]);

        $this->withHeader('X-Api-Key', $readOnly)
            ->postJson('/api/v1/erp/stock/import', ['balances' => [['material_code' => 'X', 'quantity' => 1]]])
            ->assertStatus(403);

        $writeOnly = $this->keyWith([ApiScope::StockWrite]);

        $this->withHeader('X-Api-Key', $writeOnly)
            ->getJson('/api/v1/erp/stock')
            ->assertStatus(403);

        $this->flushHeaders();

        $this->getJson('/api/v1/erp/stock-documents')->assertStatus(401);
    }

    public function test_document_export_is_scoped_to_the_keys_tenant(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        StockDocument::factory()->posted()->create(['warehouse_id' => $warehouse->id]);

        $otherTenant = Tenant::create(['name' => 'Other'])->id;
        [, $otherKey] = ApiKey::issue([
            'name' => 'Other tenant key',
            'scopes' => [ApiScope::StockRead->value],
            'tenant_id' => $otherTenant,
        ]);

        $this->withHeader('X-Api-Key', $otherKey)
            ->getJson('/api/v1/erp/stock-documents')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_acknowledging_another_tenants_document_is_not_found(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        $document = StockDocument::factory()->posted()->create(['warehouse_id' => $warehouse->id]);

        $otherTenant = Tenant::create(['name' => 'Other'])->id;
        [, $otherKey] = ApiKey::issue([
            'name' => 'Other tenant key',
            'scopes' => [ApiScope::StockWrite->value],
            'tenant_id' => $otherTenant,
        ]);

        $this->withHeader('X-Api-Key', $otherKey)
            ->postJson("/api/v1/erp/stock-documents/{$document->id}/ack")
            ->assertStatus(404);
    }
}
