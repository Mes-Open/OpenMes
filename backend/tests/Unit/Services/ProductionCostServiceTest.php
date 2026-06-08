<?php

namespace Tests\Unit\Services;

use App\Models\AdditionalCost;
use App\Models\EmployeeActivity;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\WageGroup;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Services\Production\ProductionCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionCostServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductionCostService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        config(['openmmes.default_currency' => 'PLN', 'openmmes.standard_weekly_hours' => 40]);
        $this->svc = new ProductionCostService;
    }

    private function workOrder(float $producedQty = 100): WorkOrder
    {
        return WorkOrder::factory()->create(['produced_qty' => $producedQty]);
    }

    private function workActivity(WorkOrder $wo, Worker $worker, float $hours): void
    {
        EmployeeActivity::factory()->hours($hours)->create([
            'worker_id' => $worker->id,
            'work_order_id' => $wo->id,
            'type' => 'work',
        ]);
    }

    public function test_material_cost_uses_consumed_qty_and_snapshot_price(): void
    {
        $wo = $this->workOrder();
        $material = Material::factory()->create(['unit_price' => 99]); // live price differs
        MaterialAllocation::factory()->consumed(10, snapshotPrice: 5)->create([
            'work_order_id' => $wo->id,
            'material_id' => $material->id,
        ]);

        $materials = $this->svc->materialCost($wo);

        $this->assertSame(50.0, $materials['total']);
        $this->assertCount(1, $materials['items']);
        $this->assertSame('actual', $materials['items'][0]['source']);
        $this->assertSame(5.0, $materials['items'][0]['unit_price']);
    }

    public function test_material_cost_uses_live_price_when_no_snapshot(): void
    {
        $wo = $this->workOrder();
        $material = Material::factory()->create(['unit_price' => 3]);
        MaterialAllocation::factory()->consumed(4)->create([ // no snapshot
            'work_order_id' => $wo->id,
            'material_id' => $material->id,
        ]);

        $this->assertSame(12.0, $this->svc->materialCost($wo)['total']);
    }

    public function test_material_falls_back_to_bom_with_scrap_when_no_consumption(): void
    {
        $material = Material::factory()->create(['unit_price' => 1.5, 'price_currency' => 'PLN']);
        $wo = WorkOrder::factory()->create([
            'produced_qty' => 100,
            'process_snapshot' => [
                'bom' => [[
                    'material_id' => $material->id,
                    'material_code' => $material->code,
                    'material_name' => $material->name,
                    'quantity_per_unit' => 2,
                    'scrap_percentage' => 10,
                ]],
            ],
        ]);

        $materials = $this->svc->materialCost($wo);

        // 2 * 100 * 1.10 = 220 units * 1.5 = 330
        $this->assertSame(330.0, $materials['total']);
        $this->assertSame('bom', $materials['items'][0]['source']);
    }

    public function test_labor_cost_hourly(): void
    {
        $wo = $this->workOrder();
        $worker = Worker::factory()->paidHourly(40)->create();
        $this->workActivity($wo, $worker, 2);

        $this->assertSame(80.0, $this->svc->laborCost($wo)['total']);
    }

    public function test_labor_cost_weekly_uses_effective_hourly(): void
    {
        $wo = $this->workOrder();
        $worker = Worker::factory()->paidWeekly(1600)->create(); // 1600 / 40 = 40/h
        $this->workActivity($wo, $worker, 3);

        $this->assertSame(120.0, $this->svc->laborCost($wo)['total']);
    }

    public function test_labor_cost_piece_rate_single_worker_uses_full_output(): void
    {
        $wo = $this->workOrder(50);
        $worker = Worker::factory()->paidPiece(2)->create();
        $this->workActivity($wo, $worker, 2);

        $labor = $this->svc->laborCost($wo);
        $this->assertSame(100.0, $labor['total']); // 50 pcs * 2
        $this->assertSame('pcs', $labor['items'][0]['basis_unit']);
    }

    public function test_labor_cost_piece_rate_splits_output_proportional_to_hours(): void
    {
        $wo = $this->workOrder(100);
        $a = Worker::factory()->paidPiece(2)->create();
        $b = Worker::factory()->paidPiece(2)->create();
        $this->workActivity($wo, $a, 3); // 75% of hours
        $this->workActivity($wo, $b, 1); // 25% of hours

        // a: 75 pcs * 2 = 150 ; b: 25 pcs * 2 = 50
        $this->assertSame(200.0, $this->svc->laborCost($wo)['total']);
    }

    public function test_labor_ignores_activities_without_work_order(): void
    {
        $wo = $this->workOrder();
        $worker = Worker::factory()->paidHourly(40)->create();
        EmployeeActivity::factory()->hours(5)->create([
            'worker_id' => $worker->id,
            'work_order_id' => null,
            'type' => 'work',
        ]);

        $this->assertSame(0.0, $this->svc->laborCost($wo)['total']);
    }

    public function test_labor_ignores_non_work_activity_types(): void
    {
        $wo = $this->workOrder();
        $worker = Worker::factory()->paidHourly(40)->create();
        EmployeeActivity::factory()->hours(5)->create([
            'worker_id' => $worker->id,
            'work_order_id' => $wo->id,
            'type' => 'break',
        ]);

        $this->assertSame(0.0, $this->svc->laborCost($wo)['total']);
    }

    public function test_labor_falls_back_to_wage_group_rate_as_hourly(): void
    {
        $wo = $this->workOrder();
        $group = WageGroup::create(['code' => 'WG1', 'name' => 'Std', 'base_hourly_rate' => 30, 'currency' => 'PLN']);
        $worker = Worker::factory()->create(['wage_group_id' => $group->id]); // no per-worker pay fields
        $this->workActivity($wo, $worker, 2);

        $this->assertSame(60.0, $this->svc->laborCost($wo)['total']);
    }

    public function test_labor_uses_default_pay_type_when_worker_has_none(): void
    {
        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(['key' => 'default_pay_type'], ['value' => json_encode('piece_rate')]);
        $svc = new ProductionCostService;

        $wo = $this->workOrder(50);
        // Worker has a rate but no pay_type → falls back to the default (piece_rate).
        $worker = Worker::factory()->create(['pay_rate' => 2]);
        $this->workActivity($wo, $worker, 2);

        $labor = $svc->laborCost($wo);
        $this->assertSame(100.0, $labor['total']); // 50 pcs × 2
        $this->assertSame('pcs', $labor['items'][0]['basis_unit']);
    }

    public function test_labor_falls_back_to_global_default_pay_rate(): void
    {
        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(['key' => 'default_pay_rate'], ['value' => json_encode(30)]);
        $svc = new ProductionCostService;

        $wo = $this->workOrder();
        $worker = Worker::factory()->create(); // no per-worker rate, no wage group → hourly default
        $this->workActivity($wo, $worker, 2);

        $this->assertSame(60.0, $svc->laborCost($wo)['total']); // 30/h × 2h
    }

    public function test_additional_cost_sums_entries(): void
    {
        $wo = $this->workOrder();
        AdditionalCost::factory()->create(['work_order_id' => $wo->id, 'amount' => 100, 'currency' => 'PLN']);
        AdditionalCost::factory()->create(['work_order_id' => $wo->id, 'amount' => 50, 'currency' => 'PLN']);

        $this->assertSame(150.0, $this->svc->additionalCost($wo)['total']);
    }

    public function test_cost_per_unit_is_null_when_no_output(): void
    {
        $wo = $this->workOrder(0);
        AdditionalCost::factory()->create(['work_order_id' => $wo->id, 'amount' => 100]);

        $breakdown = $this->svc->breakdown($wo);
        $this->assertSame(100.0, $breakdown['total_cost']);
        $this->assertNull($breakdown['cost_per_unit']);
    }

    public function test_mixed_currency_flag_is_raised(): void
    {
        $wo = $this->workOrder();
        AdditionalCost::factory()->create(['work_order_id' => $wo->id, 'amount' => 100, 'currency' => 'EUR']);

        $this->assertTrue($this->svc->breakdown($wo)['mixed_currency']);
    }

    public function test_reads_weekly_hours_and_currency_from_system_settings(): void
    {
        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(['key' => 'standard_weekly_hours'], ['value' => json_encode(20)]);
        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(['key' => 'default_currency'], ['value' => json_encode('EUR')]);
        $svc = new ProductionCostService; // reads settings in constructor

        $wo = $this->workOrder();
        $worker = Worker::factory()->paidWeekly(1600)->create(); // 1600 / 20 = 80/h
        $this->workActivity($wo, $worker, 2);

        $this->assertSame(160.0, $svc->laborCost($wo)['total']);
        $this->assertSame('EUR', $svc->defaultCurrency());
    }

    public function test_full_breakdown_sums_all_components(): void
    {
        $wo = $this->workOrder(10);
        $material = Material::factory()->create(['unit_price' => 2]);
        MaterialAllocation::factory()->consumed(5, snapshotPrice: 2)->create([
            'work_order_id' => $wo->id, 'material_id' => $material->id,
        ]); // 10
        $worker = Worker::factory()->paidHourly(40)->create();
        $this->workActivity($wo, $worker, 1); // 40
        AdditionalCost::factory()->create(['work_order_id' => $wo->id, 'amount' => 25]); // 25

        $b = $this->svc->breakdown($wo);
        $this->assertSame(75.0, $b['total_cost']);
        $this->assertSame(7.5, $b['cost_per_unit']);
    }
}
