<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\AdditionalCost;
use App\Models\AllocationLotPick;
use App\Models\ApiKey;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\CostSource;
use App\Models\EmployeeActivity;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use App\Models\ProductType;
use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `?include=consumption,scrap,costs` on the ERP work-order export: an ERP
 * settles a finished order (materials with lots, scrap, costs) in one call.
 */
class ErpWorkOrderSettlementTest extends TestCase
{
    use RefreshDatabase;

    /** The work-order shape without `include` — must never change. */
    private const BASE_KEYS = [
        'order_no', 'customer_order_no', 'status', 'planned_qty', 'produced_qty',
        'unit_price', 'counting_source', 'line_code', 'product_type_code',
        'completed_at', 'updated_at',
    ];

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
            'name' => 'ERP settlement key',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $this->tenantId,
        ]);

        return $plaintext;
    }

    private function doneOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'tenant_id' => $this->tenantId,
            'line_id' => $this->line->id,
            'product_type_id' => $this->product->id,
            'status' => WorkOrder::STATUS_DONE,
            'planned_qty' => 100,
            'produced_qty' => 100,
            'completed_at' => now(),
        ], $overrides));
    }

    /**
     * A finished order with one consumed allocation (picked from a lot), one
     * scrap entry on a batch step, one additional cost and one hour of work.
     */
    private function settledOrder(string $orderNo, string $suffix = 'A'): WorkOrder
    {
        $wo = $this->doneOrder(['order_no' => $orderNo]);

        $material = Material::factory()->create([
            'code' => "MAT-{$suffix}",
            'name' => "Flour {$suffix}",
            'unit_of_measure' => 'kg',
            'unit_price' => 2,
            'price_currency' => 'PLN',
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'batch_number' => 1]);
        $step = BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 2, 'name' => 'Mixing']);

        $allocation = MaterialAllocation::factory()->create([
            'work_order_id' => $wo->id,
            'batch_id' => $batch->id,
            'material_id' => $material->id,
            'allocated_qty' => 13,
            'consumed_qty' => 12.5,
            'scrap_qty' => 0.5,
            'status' => MaterialAllocation::STATUS_CONSUMED,
            'consumed_at' => now(),
            'unit_price_snapshot' => 2,
            'price_currency_snapshot' => 'PLN',
        ]);
        $lot = MaterialLot::factory()->create(['material_id' => $material->id, 'lot_number' => "LOT-{$suffix}-1"]);
        AllocationLotPick::create([
            'material_allocation_id' => $allocation->id,
            'material_lot_id' => $lot->id,
            'picked_qty' => 13,
            'picking_strategy' => AllocationLotPick::STRATEGY_MANUAL,
        ]);

        $reason = ScrapReason::factory()->create(['code' => "BURNT-{$suffix}", 'name' => 'Burnt']);
        ScrapEntry::factory()->create([
            'work_order_id' => $wo->id,
            'scrap_reason_id' => $reason->id,
            'batch_step_id' => $step->id,
            'quantity' => 3,
            'notes' => 'Oven too hot',
        ]);

        $source = CostSource::create(['code' => "TRANS-{$suffix}", 'name' => 'Transport', 'currency' => 'PLN', 'is_active' => true]);
        AdditionalCost::factory()->create([
            'work_order_id' => $wo->id,
            'cost_source_id' => $source->id,
            'description' => 'Courier',
            'amount' => 40,
            'currency' => 'PLN',
        ]);

        $worker = Worker::factory()->create(['code' => "W-{$suffix}", 'pay_type' => 'hourly', 'pay_rate' => 30]);
        EmployeeActivity::factory()->create([
            'worker_id' => $worker->id,
            'work_order_id' => $wo->id,
            'type' => 'work',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
        ]);

        return $wo;
    }

    private function getAs(string $url, array $scopes = [ApiScope::ProductionRead])
    {
        return $this->withHeader('X-Api-Key', $this->keyWith($scopes))->getJson($url);
    }

    public function test_show_with_all_includes_returns_every_settlement_section(): void
    {
        $wo = $this->settledOrder('WO-SETTLE');

        $response = $this->getAs("/api/v1/erp/work-orders/{$wo->id}?include=consumption,scrap,costs")->assertOk();

        $data = $response->json('data');
        $this->assertSame('WO-SETTLE', $data['order_no']);

        // Consumption: material code, unit, quantities and the lot it came from.
        $this->assertCount(1, $data['consumption']);
        $line = $data['consumption'][0];
        $this->assertSame('MAT-A', $line['material_code']);
        $this->assertSame('kg', $line['unit_of_measure']);
        $this->assertEquals(12.5, $line['consumed_qty']);
        $this->assertEquals(0.5, $line['scrap_qty']);
        $this->assertSame([['lot_number' => 'LOT-A-1', 'quantity' => 13]], $line['lots']);

        // Scrap: quantity, reason and the step it was reported on.
        $this->assertEquals(3, $data['scrap']['total_qty']);
        $entry = $data['scrap']['entries'][0];
        $this->assertEquals(3, $entry['quantity']);
        $this->assertSame('BURNT-A', $entry['reason']['code']);
        $this->assertSame('Burnt', $entry['reason']['name']);
        $this->assertSame(['batch_number' => 1, 'step_number' => 2, 'name' => 'Mixing'], $entry['step']);
        $this->assertArrayNotHasKey('reported_by', $entry);

        // Costs: actual materials (12.5 × 2), labor (1 h × 30), additional (40).
        $costs = $data['costs'];
        $this->assertEquals(25, $costs['materials']['total']);
        $this->assertSame('actual', $costs['materials']['items'][0]['source']);
        $this->assertEquals(30, $costs['labor']['total']);
        $this->assertSame('W-A', $costs['labor']['items'][0]['worker_code']);
        $this->assertArrayNotHasKey('worker_name', $costs['labor']['items'][0]);
        $this->assertEquals(40, $costs['additional']['total']);
        $this->assertSame([
            'description' => 'Courier',
            'cost_source_code' => 'TRANS-A',
            'cost_source_name' => 'Transport',
            'amount' => 40,
            'currency' => 'PLN',
        ], $costs['additional']['items'][0]);
        $this->assertEquals(95, $costs['total_cost']);
        $this->assertEquals(0.95, $costs['cost_per_unit']);
        $this->assertArrayNotHasKey('machine', $costs);
    }

    public function test_each_include_adds_only_its_own_section(): void
    {
        $wo = $this->settledOrder('WO-ONE');

        foreach (['consumption', 'scrap', 'costs'] as $section) {
            $data = $this->getAs("/api/v1/erp/work-orders/{$wo->id}?include={$section}")->assertOk()->json('data');

            $this->assertEqualsCanonicalizing([...self::BASE_KEYS, $section], array_keys($data), $section);
        }
    }

    public function test_include_tolerates_spaces_and_duplicates(): void
    {
        $wo = $this->settledOrder('WO-SPACES');

        $data = $this->getAs("/api/v1/erp/work-orders/{$wo->id}?include=".urlencode(' scrap , scrap,costs'))
            ->assertOk()->json('data');

        $this->assertEqualsCanonicalizing([...self::BASE_KEYS, 'scrap', 'costs'], array_keys($data));
    }

    public function test_without_include_the_shape_is_unchanged(): void
    {
        $wo = $this->settledOrder('WO-PLAIN');

        $show = $this->getAs("/api/v1/erp/work-orders/{$wo->id}")->assertOk()->json('data');
        $this->assertSame(self::BASE_KEYS, array_keys($show));

        $list = $this->getAs('/api/v1/erp/production/completions')->assertOk()->json('data');
        $this->assertSame(self::BASE_KEYS, array_keys($list[0]));
    }

    public function test_completions_carry_the_requested_sections_per_order(): void
    {
        $this->settledOrder('WO-L1', 'A');
        $this->settledOrder('WO-L2', 'B');

        $rows = collect($this->getAs('/api/v1/erp/production/completions?include=consumption,scrap,costs&limit=10')
            ->assertOk()->json('data'))->keyBy('order_no');

        $this->assertSame('MAT-A', $rows['WO-L1']['consumption'][0]['material_code']);
        $this->assertSame('MAT-B', $rows['WO-L2']['consumption'][0]['material_code']);
        $this->assertSame('BURNT-B', $rows['WO-L2']['scrap']['entries'][0]['reason']['code']);
        $this->assertSame('TRANS-A', $rows['WO-L1']['costs']['additional']['items'][0]['cost_source_code']);
    }

    public function test_unknown_include_is_rejected(): void
    {
        $wo = $this->doneOrder();

        foreach (['foo', 'scrap,bogus', 'scrap,,costs'] as $include) {
            $this->getAs("/api/v1/erp/work-orders/{$wo->id}?include={$include}")
                ->assertStatus(422)->assertJsonValidationErrors('include');
            $this->getAs("/api/v1/erp/production/completions?include={$include}")
                ->assertStatus(422)->assertJsonValidationErrors('include');
        }
    }

    public function test_limit_is_capped_lower_when_sections_are_included(): void
    {
        $this->getAs('/api/v1/erp/production/completions?include=scrap&limit=101')
            ->assertStatus(422)->assertJsonValidationErrors('limit');
        $this->getAs('/api/v1/erp/production/completions?include=scrap&limit=100')->assertOk();
        // Without include the old ceiling still applies.
        $this->getAs('/api/v1/erp/production/completions?limit=500')->assertOk();
    }

    public function test_include_requires_the_production_read_scope(): void
    {
        $wo = $this->settledOrder('WO-SCOPE');

        $this->getAs("/api/v1/erp/work-orders/{$wo->id}?include=costs", [ApiScope::QualityRead])->assertStatus(403);
        $this->getAs('/api/v1/erp/production/completions?include=costs', [ApiScope::QualityRead])->assertStatus(403);
    }

    public function test_include_requires_an_api_key(): void
    {
        $wo = $this->doneOrder();

        $this->getJson("/api/v1/erp/work-orders/{$wo->id}?include=costs")->assertStatus(401);
        $this->getJson('/api/v1/erp/production/completions?include=costs')->assertStatus(401);
    }

    public function test_another_tenant_order_and_its_settlement_never_leak(): void
    {
        $this->settledOrder('WO-MINE', 'A');

        app(TenantContext::class)->set($this->otherTenantId);
        $foreign = WorkOrder::factory()->create([
            'tenant_id' => $this->otherTenantId,
            'order_no' => 'WO-FOREIGN',
            'status' => WorkOrder::STATUS_DONE,
            'completed_at' => now(),
        ]);
        ScrapEntry::factory()->create(['work_order_id' => $foreign->id, 'quantity' => 99]);
        AdditionalCost::factory()->create(['work_order_id' => $foreign->id, 'description' => 'Foreign cost', 'amount' => 999]);
        app(TenantContext::class)->set($this->tenantId);

        $this->getAs("/api/v1/erp/work-orders/{$foreign->id}?include=consumption,scrap,costs")->assertStatus(404);

        $response = $this->getAs('/api/v1/erp/production/completions?include=consumption,scrap,costs')->assertOk();
        $rows = collect($response->json('data'));
        $this->assertSame(['WO-MINE'], $rows->pluck('order_no')->all());
        $this->assertEquals(3, $rows[0]['scrap']['total_qty']);
        $this->assertStringNotContainsString('Foreign cost', $response->getContent());
    }

    public function test_soft_deleted_scrap_and_costs_are_excluded(): void
    {
        $wo = $this->settledOrder('WO-DELETED');
        $wo->scrapEntries()->first()->delete();
        $wo->additionalCosts()->first()->delete();

        $data = $this->getAs("/api/v1/erp/work-orders/{$wo->id}?include=scrap,costs")->assertOk()->json('data');

        $this->assertSame([], $data['scrap']['entries']);
        $this->assertEquals(0, $data['scrap']['total_qty']);
        $this->assertSame([], $data['costs']['additional']['items']);
        $this->assertEquals(0, $data['costs']['additional']['total']);
    }

    public function test_archived_material_and_lot_keep_their_codes_in_consumption(): void
    {
        $wo = $this->settledOrder('WO-ARCHIVED');
        $allocation = $wo->materialAllocations()->with('material', 'lotPicks.lot')->first();
        $allocation->lotPicks->first()->lot->delete();
        $allocation->material->delete();

        $data = $this->getAs("/api/v1/erp/work-orders/{$wo->id}?include=consumption,costs")->assertOk()->json('data');

        $this->assertSame('MAT-A', $data['consumption'][0]['material_code']);
        $this->assertSame('kg', $data['consumption'][0]['unit_of_measure']);
        $this->assertSame('LOT-A-1', $data['consumption'][0]['lots'][0]['lot_number']);
        $this->assertSame('MAT-A', $data['costs']['materials']['items'][0]['material_code']);
    }

    public function test_order_without_settlement_data_returns_empty_sections(): void
    {
        $wo = $this->doneOrder(['order_no' => 'WO-EMPTY']);

        $data = $this->getAs("/api/v1/erp/work-orders/{$wo->id}?include=consumption,scrap,costs")->assertOk()->json('data');

        $this->assertSame([], $data['consumption']);
        $this->assertSame([], $data['scrap']['entries']);
        $this->assertEquals(0, $data['scrap']['total_qty']);
        $this->assertSame([], $data['costs']['additional']['items']);
        $this->assertSame([], $data['costs']['labor']['items']);
        $this->assertEquals(0, $data['costs']['total_cost']);
    }

    public function test_completions_with_includes_run_a_constant_number_of_queries(): void
    {
        $url = '/api/v1/erp/production/completions?include=consumption,scrap,costs&limit=100';
        $key = $this->keyWith([ApiScope::ProductionRead]);

        $count = function (int $expectedOrders) use ($url, $key): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $response = $this->withHeader('X-Api-Key', $key)->getJson($url)->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            // Every order really carries data, so the count covers loaded rows.
            $rows = collect($response->json('data'));
            $this->assertCount($expectedOrders, $rows);
            $rows->each(fn (array $row) => $this->assertCount(1, $row['consumption'][0]['lots']));

            // The API-key middleware re-points the tenant context per request;
            // restore it for the fixtures created next.
            app(TenantContext::class)->set($this->tenantId);

            return $queries;
        };

        $this->settledOrder('WO-N1', 'A');
        $this->settledOrder('WO-N2', 'B');
        // Warm-up: the first request also touches the key's last_used_at and
        // reads the cost settings once — one-off work, not per-order work.
        $count(2);
        $few = $count(2);

        foreach (['C', 'D', 'E', 'F', 'G'] as $suffix) {
            $this->settledOrder("WO-N-{$suffix}", $suffix);
        }
        $many = $count(7);

        $this->assertSame($few, $many, 'Query count grew with the number of orders (N+1).');
    }
}
