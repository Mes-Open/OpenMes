<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\WorkOrder;
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

    // ── External source fields (system, reference, customer, currency, extra data) ──

    private function import(array $orders): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::OrdersImport]))
            ->postJson('/api/v1/erp/work-orders/import', ['orders' => $orders]);
    }

    public function test_import_stores_external_source_fields_on_create(): void
    {
        $this->import([$this->validOrder([
            'order_no' => 'ERP-SRC-1',
            'unit_price' => 12.5,
            'price_currency' => ' eur ',
            'external_system' => 'pantheon',
            'external_ref' => 'SO-4711/3',
            'customer_name' => 'Acme GmbH',
            'extra_data' => ['color' => 'red', 'sizes' => ['S', 'M'], 'rush' => true, 'note' => null],
        ])])->assertOk()->assertJsonPath('data.imported', 1);

        $order = WorkOrder::where('order_no', 'ERP-SRC-1')->firstOrFail();
        $this->assertSame('pantheon', $order->external_system);
        $this->assertSame('SO-4711/3', $order->external_ref);
        $this->assertSame('Acme GmbH', $order->customer_name);
        $this->assertSame('EUR', $order->price_currency);
        $this->assertSame(['color' => 'red', 'sizes' => ['S', 'M'], 'rush' => true, 'note' => null], $order->extra_data);
    }

    public function test_import_updates_external_source_fields_and_merges_extra_data(): void
    {
        $this->import([$this->validOrder([
            'order_no' => 'ERP-SRC-2',
            'external_system' => 'pantheon',
            'external_ref' => 'SO-1/1',
            'customer_name' => 'Old Name',
            'extra_data' => ['a' => 1, 'b' => 2],
        ])])->assertOk();

        $this->import([$this->validOrder([
            'order_no' => 'ERP-SRC-2',
            'external_ref' => 'SO-1/2',
            'customer_name' => 'New Name',
            'price_currency' => 'USD',
            'extra_data' => ['b' => 3, 'c' => [1, 2]],
        ])])->assertOk()->assertJsonPath('data.updated', 1);

        $order = WorkOrder::where('order_no', 'ERP-SRC-2')->firstOrFail();
        $this->assertSame('pantheon', $order->external_system, 'an absent field is left as stored');
        $this->assertSame('SO-1/2', $order->external_ref);
        $this->assertSame('New Name', $order->customer_name);
        $this->assertSame('USD', $order->price_currency);
        $this->assertSame(['a' => 1, 'b' => 3, 'c' => [1, 2]], $order->extra_data);
    }

    public function test_import_without_new_fields_leaves_them_untouched(): void
    {
        $this->import([$this->validOrder([
            'order_no' => 'ERP-SRC-3',
            'external_system' => 'sap',
            'external_ref' => 'VBAP-10',
            'extra_data' => ['k' => 'v'],
        ])])->assertOk();

        $this->import([$this->validOrder([
            'order_no' => 'ERP-SRC-3',
            'planned_qty' => 300,
            'external_ref' => null,
        ])])->assertOk()->assertJsonPath('data.updated', 1);

        $order = WorkOrder::where('order_no', 'ERP-SRC-3')->firstOrFail();
        $this->assertEquals(300, (float) $order->planned_qty);
        $this->assertSame('sap', $order->external_system);
        $this->assertSame('VBAP-10', $order->external_ref, 'null leaves the stored value alone');
        $this->assertNull($order->price_currency);
        $this->assertNull($order->customer_name);
        $this->assertSame(['k' => 'v'], $order->extra_data);
    }

    public function test_import_without_source_fields_creates_order_as_before(): void
    {
        $this->import([$this->validOrder(['order_no' => 'ERP-PLAIN'])])->assertOk();

        $order = WorkOrder::where('order_no', 'ERP-PLAIN')->firstOrFail();
        $this->assertNull($order->external_system);
        $this->assertNull($order->external_ref);
        $this->assertNull($order->customer_name);
        $this->assertNull($order->price_currency);
        $this->assertNull($order->extra_data);
    }

    public function test_import_accepts_an_empty_extra_data_object_and_currency_without_price(): void
    {
        $this->import([$this->validOrder([
            'order_no' => 'ERP-EDGE',
            'price_currency' => 'PLN',
            'extra_data' => new \stdClass,
        ])])->assertOk()->assertJsonPath('data.imported', 1);

        $this->assertSame('PLN', WorkOrder::where('order_no', 'ERP-EDGE')->value('price_currency'));
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidSourceFields(): array
    {
        return [
            'unknown currency' => ['price_currency', 'XYZ'],
            'currency too long' => ['price_currency', 'EURO'],
            'extra_data is a list' => ['extra_data', [1, 2]],
            'extra_data is a string' => ['extra_data', 'red'],
            'extra_data nested object' => ['extra_data', ['a' => ['b' => 1]]],
            'extra_data list of lists' => ['extra_data', ['a' => [[1]]]],
            'extra_data over 16 KB' => ['extra_data', ['blob' => str_repeat('x', 17000)]],
            'extra_data too many keys' => ['extra_data', array_combine(
                array_map(fn ($i) => "k{$i}", range(1, 101)),
                array_fill(0, 101, 1),
            )],
            'external_system too long' => ['external_system', str_repeat('s', 51)],
            'external_ref too long' => ['external_ref', str_repeat('r', 101)],
            'customer_name too long' => ['customer_name', str_repeat('c', 256)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSourceFields')]
    public function test_import_rejects_invalid_source_fields_with_422(string $field, mixed $value): void
    {
        $this->import([$this->validOrder(['order_no' => 'ERP-BAD', $field => $value])])
            ->assertStatus(422)
            ->assertJsonValidationErrors("orders.0.{$field}");

        $this->assertDatabaseMissing('work_orders', ['order_no' => 'ERP-BAD']);
    }

    public function test_csv_import_ignores_an_unsupported_mapped_currency(): void
    {
        // The legacy CSV mapping accepts any field name, so price_currency can
        // reach the shared import path without the ERP request's validation.
        $service = app(\App\Services\CsvImport\WorkOrderImportService::class);
        $row = fn (string $orderNo, string $currency) => [
            'row_number' => 2,
            'order_no' => $orderNo,
            'line_code' => 'L1',
            'product_type_code' => 'P1',
            'planned_qty' => 10,
            'unit_price' => 5,
            'price_currency' => $currency,
        ];

        $result = $service->import([$row('CSV-BAD', 'XYZ'), $row('CSV-OK', ' eur ')], 'update_or_create');

        $this->assertSame(2, $result['successful']);
        $this->assertNull(WorkOrder::where('order_no', 'CSV-BAD')->value('price_currency'));
        $this->assertSame('EUR', WorkOrder::where('order_no', 'CSV-OK')->value('price_currency'));
    }
}
