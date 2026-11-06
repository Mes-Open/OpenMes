<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Issue;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Support\CurrencyRegistry;
use App\Support\ModuleRegistry;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpExportTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private int $otherTenantId;

    private Line $line;

    private ProductType $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'Primary'])->id;
        $this->otherTenantId = Tenant::create(['name' => 'Other'])->id;

        app(TenantContext::class)->set($this->tenantId);

        $this->line = Line::factory()->create(['code' => 'LX']);
        $this->product = ProductType::factory()->create(['code' => 'PX']);
    }

    private function keyWith(array $scopes): string
    {
        [, $plaintext] = ApiKey::issue([
            'name' => 'ERP export key',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $this->tenantId,
        ]);

        return $plaintext;
    }

    private function workOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'tenant_id' => $this->tenantId,
            'line_id' => $this->line->id,
            'product_type_id' => $this->product->id,
        ], $overrides));
    }

    public function test_completions_returns_only_completed_orders_by_default(): void
    {
        $done = $this->workOrder([
            'order_no' => 'WO-DONE',
            'status' => WorkOrder::STATUS_DONE,
            'produced_qty' => 100,
            'planned_qty' => 100,
            'completed_at' => now(),
        ]);
        $this->workOrder(['order_no' => 'WO-PENDING', 'status' => WorkOrder::STATUS_PENDING]);

        $response = $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))
            ->getJson('/api/v1/erp/production/completions');

        $response->assertOk();
        $orders = collect($response->json('data'))->pluck('order_no');
        $this->assertContains('WO-DONE', $orders);
        $this->assertNotContains('WO-PENDING', $orders);

        $row = collect($response->json('data'))->firstWhere('order_no', 'WO-DONE');
        $this->assertEquals(100.0, $row['produced_qty']);
        $this->assertSame('PX', $row['product_type_code']);
        $this->assertSame('LX', $row['line_code']);
        $this->assertArrayHasKey('next_cursor', $response->json('meta'));
    }

    public function test_completions_are_isolated_to_the_key_tenant(): void
    {
        $this->workOrder(['order_no' => 'WO-MINE', 'status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);

        // Another tenant's completed order must never appear.
        app(TenantContext::class)->set($this->otherTenantId);
        WorkOrder::factory()->create([
            'tenant_id' => $this->otherTenantId,
            'order_no' => 'WO-OTHER',
            'status' => WorkOrder::STATUS_DONE,
            'completed_at' => now(),
        ]);
        app(TenantContext::class)->set($this->tenantId);

        $response = $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))
            ->getJson('/api/v1/erp/production/completions');

        $orders = collect($response->json('data'))->pluck('order_no');
        $this->assertContains('WO-MINE', $orders);
        $this->assertNotContains('WO-OTHER', $orders);
    }

    public function test_completions_require_the_production_read_scope(): void
    {
        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::QualityRead]))
            ->getJson('/api/v1/erp/production/completions')
            ->assertStatus(403);
    }

    public function test_show_returns_a_single_order(): void
    {
        $wo = $this->workOrder(['order_no' => 'WO-SHOW', 'status' => WorkOrder::STATUS_IN_PROGRESS]);

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))
            ->getJson("/api/v1/erp/work-orders/{$wo->id}")
            ->assertOk()
            ->assertJsonPath('data.order_no', 'WO-SHOW');
    }

    public function test_show_does_not_leak_another_tenant_order(): void
    {
        app(TenantContext::class)->set($this->otherTenantId);
        $foreign = WorkOrder::factory()->create(['tenant_id' => $this->otherTenantId, 'order_no' => 'FOREIGN']);
        app(TenantContext::class)->set($this->tenantId);

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))
            ->getJson("/api/v1/erp/work-orders/{$foreign->id}")
            ->assertStatus(404);
    }

    public function test_quality_export_returns_issues_scoped_to_tenant(): void
    {
        $wo = $this->workOrder(['order_no' => 'WO-Q', 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        Issue::factory()->create([
            'work_order_id' => $wo->id,
            'title' => 'Cracked housing',
            'non_conforming_qty' => 3,
        ]);

        // Issue on another tenant's work order — must be excluded.
        app(TenantContext::class)->set($this->otherTenantId);
        $otherWo = WorkOrder::factory()->create(['tenant_id' => $this->otherTenantId, 'order_no' => 'WO-Q-OTHER']);
        Issue::factory()->create(['work_order_id' => $otherWo->id, 'title' => 'Foreign issue']);
        app(TenantContext::class)->set($this->tenantId);

        $response = $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::QualityRead]))
            ->getJson('/api/v1/erp/quality/issues');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Cracked housing', $titles);
        $this->assertNotContains('Foreign issue', $titles);
    }

    public function test_quality_export_requires_the_quality_read_scope(): void
    {
        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))
            ->getJson('/api/v1/erp/quality/issues')
            ->assertStatus(403);
    }

    public function test_erp_api_404s_when_the_module_is_disabled(): void
    {
        // Persist an enabled-module set that omits 'erp' → module off.
        ModuleRegistry::save(['reports']);

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))
            ->getJson('/api/v1/erp/production/completions')
            ->assertStatus(404);
    }

    // ── External source fields ──────────────────────────────────────────────

    private function sourcedOrder(array $overrides = []): WorkOrder
    {
        return $this->workOrder(array_merge([
            'order_no' => 'WO-SRC',
            'status' => WorkOrder::STATUS_DONE,
            'completed_at' => now(),
            'unit_price' => 12.5,
            'price_currency' => 'EUR',
            'external_system' => 'pantheon',
            'external_ref' => 'SO-4711/3',
            'customer_name' => 'Acme GmbH',
            'extra_data' => ['color' => 'red', 'sizes' => ['S', 'M']],
        ], $overrides));
    }

    public function test_show_includes_external_source_and_currency(): void
    {
        $wo = $this->sourcedOrder();

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))
            ->getJson("/api/v1/erp/work-orders/{$wo->id}")
            ->assertOk()
            ->assertJsonPath('data.price_currency', 'EUR')
            ->assertJsonPath('data.external_system', 'pantheon')
            ->assertJsonPath('data.external_ref', 'SO-4711/3')
            ->assertJsonPath('data.customer_name', 'Acme GmbH')
            ->assertJsonPath('data.extra_data', ['color' => 'red', 'sizes' => ['S', 'M']]);
    }

    public function test_completions_include_external_source_fields(): void
    {
        $this->sourcedOrder();

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))
            ->getJson('/api/v1/erp/production/completions')
            ->assertOk()
            ->assertJsonPath('data.0.order_no', 'WO-SRC')
            ->assertJsonPath('data.0.external_system', 'pantheon')
            ->assertJsonPath('data.0.external_ref', 'SO-4711/3')
            ->assertJsonPath('data.0.customer_name', 'Acme GmbH')
            ->assertJsonPath('data.0.price_currency', 'EUR')
            ->assertJsonPath('data.0.extra_data.color', 'red');
    }

    public function test_price_currency_falls_back_to_installation_currency_when_unit_price_set(): void
    {
        CurrencyRegistry::save('USD');
        $priced = $this->sourcedOrder(['order_no' => 'WO-PRICED', 'price_currency' => null]);
        $unpriced = $this->sourcedOrder(['order_no' => 'WO-UNPRICED', 'price_currency' => null, 'unit_price' => null]);
        $key = $this->keyWith([ApiScope::ProductionRead]);

        $this->withHeader('X-Api-Key', $key)->getJson("/api/v1/erp/work-orders/{$priced->id}")
            ->assertOk()->assertJsonPath('data.price_currency', 'USD');
        $this->withHeader('X-Api-Key', $key)->getJson("/api/v1/erp/work-orders/{$unpriced->id}")
            ->assertOk()->assertJsonPath('data.price_currency', null);
    }

    public function test_order_without_source_fields_exports_nulls(): void
    {
        $wo = $this->workOrder(['order_no' => 'WO-PLAIN', 'unit_price' => null]);

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::ProductionRead]))
            ->getJson("/api/v1/erp/work-orders/{$wo->id}")
            ->assertOk()
            ->assertJsonPath('data.order_no', 'WO-PLAIN')
            ->assertJsonPath('data.external_system', null)
            ->assertJsonPath('data.external_ref', null)
            ->assertJsonPath('data.customer_name', null)
            ->assertJsonPath('data.price_currency', null)
            ->assertJsonPath('data.extra_data', null);
    }
}
