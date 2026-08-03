<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpWorkOrderImportTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'ERP Tenant'])->id;

        // Seed master data under the same tenant so the importer resolves it.
        app(TenantContext::class)->set($this->tenantId);

        Line::factory()->create(['code' => 'L1']);
        $product = ProductType::factory()->create(['code' => 'P1']);
        ProcessTemplate::factory()->withSteps(2)->create([
            'product_type_id' => $product->id,
            'is_active' => true,
        ]);
    }

    private function keyWith(array $scopes): string
    {
        [, $plaintext] = ApiKey::issue([
            'name' => 'ERP test key',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $this->tenantId,
        ]);

        return $plaintext;
    }

    private function validOrder(array $overrides = []): array
    {
        return array_merge([
            'order_no' => 'ERP-1001',
            'line_code' => 'L1',
            'product_type_code' => 'P1',
            'planned_qty' => 250,
        ], $overrides);
    }

    public function test_import_creates_work_orders_scoped_to_key_tenant(): void
    {
        $key = $this->keyWith([ApiScope::OrdersImport]);

        $response = $this->withHeader('X-Api-Key', $key)->postJson('/api/v1/erp/work-orders/import', [
            'orders' => [
                $this->validOrder(['order_no' => 'ERP-1001', 'customer_order_no' => 'PO-77', 'unit_price' => 12.5]),
                $this->validOrder(['order_no' => 'ERP-1002', 'planned_qty' => 40]),
            ],
        ]);

        $response->assertOk();
        $this->assertSame(2, $response->json('data.imported'));
        $this->assertSame(0, $response->json('data.updated'));
        $this->assertSame([], $response->json('data.errors'));

        $this->assertDatabaseHas('work_orders', [
            'order_no' => 'ERP-1001',
            'customer_order_no' => 'PO-77',
            'tenant_id' => $this->tenantId,
            'status' => 'PENDING',
        ]);
    }

    public function test_import_returns_207_with_structured_row_errors(): void
    {
        $key = $this->keyWith([ApiScope::OrdersImport]);

        $response = $this->withHeader('X-Api-Key', $key)->postJson('/api/v1/erp/work-orders/import', [
            'orders' => [
                $this->validOrder(['order_no' => 'ERP-2001']),
                $this->validOrder(['order_no' => 'ERP-2002', 'product_type_code' => 'NOPE']),
            ],
        ]);

        $response->assertStatus(207);
        $this->assertSame(1, $response->json('data.imported'));
        $this->assertSame(2, $response->json('data.errors.0.row'));
        $this->assertSame('product_type_code', $response->json('data.errors.0.field'));
        $this->assertDatabaseMissing('work_orders', ['order_no' => 'ERP-2002']);
    }

    public function test_import_rejects_malformed_payload_with_422(): void
    {
        $key = $this->keyWith([ApiScope::OrdersImport]);

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/erp/work-orders/import', ['orders' => []])
            ->assertStatus(422);

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/erp/work-orders/import', [
                'orders' => [['line_code' => 'L1', 'product_type_code' => 'P1', 'planned_qty' => 5]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('orders.0.order_no');
    }

    public function test_import_requires_an_api_key(): void
    {
        $this->postJson('/api/v1/erp/work-orders/import', ['orders' => [$this->validOrder()]])
            ->assertStatus(401);
    }

    public function test_import_requires_the_orders_import_scope(): void
    {
        $key = $this->keyWith([ApiScope::ProductionRead]);

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/erp/work-orders/import', ['orders' => [$this->validOrder()]])
            ->assertStatus(403);
    }

    public function test_import_rejects_an_inactive_key(): void
    {
        [$model, $plaintext] = ApiKey::issue([
            'name' => 'disabled',
            'scopes' => [ApiScope::OrdersImport->value],
            'tenant_id' => $this->tenantId,
        ]);
        $model->update(['is_active' => false]);

        $this->withHeader('X-Api-Key', $plaintext)
            ->postJson('/api/v1/erp/work-orders/import', ['orders' => [$this->validOrder()]])
            ->assertStatus(401);
    }
}
