<?php

namespace Tests\Feature\Material;

use App\Models\BomItem;
use App\Models\Line;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\NetRequirementsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Basic MRP (#90): BOM explosion of planned work orders, netting against
 * on-hand stock, and the shortage list.
 */
class NetRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private NetRequirementsService $service;

    private Carbon $from;

    private Carbon $to;

    private Carbon $due;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NetRequirementsService::class);
        $this->from = today()->startOfDay();
        $this->to = today()->addDays(30)->endOfDay();
        $this->due = today()->addDays(5);
    }

    /**
     * Create a product type whose active template has the given BOM lines.
     *
     * @param  array<int, array{material: Material, qty: float, scrap?: float}>  $bom
     */
    private function productWithBom(array $bom): ProductType
    {
        $pt = ProductType::factory()->create();
        $tpl = ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'is_active' => true, 'version' => 1]);
        foreach ($bom as $line) {
            BomItem::factory()->create([
                'process_template_id' => $tpl->id,
                'material_id' => $line['material']->id,
                'quantity_per_unit' => $line['qty'],
                'scrap_percentage' => $line['scrap'] ?? 0,
            ]);
        }

        return $pt;
    }

    private function plannedWo(ProductType $pt, float $qty, ?int $lineId = null, ?string $status = null): WorkOrder
    {
        return WorkOrder::factory()->create([
            'product_type_id' => $pt->id,
            'planned_qty' => $qty,
            'status' => $status ?? WorkOrder::STATUS_PENDING,
            'due_date' => $this->due,
            'line_id' => $lineId ?? Line::factory()->create()->id,
        ]);
    }

    private function rowFor(array $report, int $materialId): ?array
    {
        foreach ($report['requirements'] as $r) {
            if ($r['material_id'] === $materialId) {
                return $r;
            }
        }

        return null;
    }

    public function test_explodes_planned_work_order_against_bom(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $pt = $this->productWithBom([['material' => $material, 'qty' => 2]]);
        $this->plannedWo($pt, 100);

        $report = $this->service->report($this->from, $this->to);
        $row = $this->rowFor($report, $material->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(200, $row['required_qty'], 0.001); // 2 × 100
        $this->assertEqualsWithDelta(0, $row['available_qty'], 0.001);
        $this->assertEqualsWithDelta(200, $row['net_qty'], 0.001);
        $this->assertTrue($row['is_short']);
    }

    public function test_nets_gross_against_on_hand_stock(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 150]);
        $pt = $this->productWithBom([['material' => $material, 'qty' => 2]]);
        $this->plannedWo($pt, 100); // gross 200

        $row = $this->rowFor($this->service->report($this->from, $this->to), $material->id);

        $this->assertEqualsWithDelta(200, $row['required_qty'], 0.001);
        $this->assertEqualsWithDelta(150, $row['available_qty'], 0.001);
        $this->assertEqualsWithDelta(50, $row['net_qty'], 0.001); // 200 − 150
        $this->assertTrue($row['is_short']);
    }

    public function test_no_shortage_when_stock_covers_demand(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 500]);
        $pt = $this->productWithBom([['material' => $material, 'qty' => 2]]);
        $this->plannedWo($pt, 100); // gross 200 ≤ 500

        $report = $this->service->report($this->from, $this->to);
        $row = $this->rowFor($report, $material->id);

        $this->assertEqualsWithDelta(0, $row['net_qty'], 0.001);
        $this->assertFalse($row['is_short']);
        $this->assertSame([], $report['shortages']);
        $this->assertSame(0, $report['totals']['shortage_components']);
    }

    public function test_scrap_percentage_is_included_in_gross(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $pt = $this->productWithBom([['material' => $material, 'qty' => 1, 'scrap' => 10]]);
        $this->plannedWo($pt, 100); // 1 × 100 × 1.10 = 110

        $row = $this->rowFor($this->service->report($this->from, $this->to), $material->id);
        $this->assertEqualsWithDelta(110, $row['required_qty'], 0.001);
    }

    public function test_demand_from_multiple_work_orders_aggregates_and_lists_them(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $pt = $this->productWithBom([['material' => $material, 'qty' => 1]]);
        $woA = $this->plannedWo($pt, 30);
        $woB = $this->plannedWo($pt, 70, status: WorkOrder::STATUS_ACCEPTED);

        $report = $this->service->report($this->from, $this->to);
        $row = $this->rowFor($report, $material->id);

        $this->assertEqualsWithDelta(100, $row['required_qty'], 0.001); // 30 + 70
        $this->assertContains($woA->order_no, $row['related_work_orders']);
        $this->assertContains($woB->order_no, $row['related_work_orders']);
    }

    public function test_only_pending_and_accepted_work_orders_create_demand(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $pt = $this->productWithBom([['material' => $material, 'qty' => 1]]);
        // In-progress and done orders must NOT add to gross (materials already
        // allocated / consumed — would double-count).
        $this->plannedWo($pt, 50, status: WorkOrder::STATUS_IN_PROGRESS);
        $this->plannedWo($pt, 50, status: WorkOrder::STATUS_DONE);

        $report = $this->service->report($this->from, $this->to);
        $this->assertSame([], $report['requirements']);
        $this->assertSame(0, $report['totals']['work_orders']);
    }

    public function test_filters_by_period_and_line(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $pt = $this->productWithBom([['material' => $material, 'qty' => 1]]);
        $line = Line::factory()->create();

        $this->plannedWo($pt, 100, lineId: $line->id); // in window, on line
        // Outside the window (due far in the future).
        WorkOrder::factory()->create([
            'product_type_id' => $pt->id, 'planned_qty' => 999,
            'status' => WorkOrder::STATUS_PENDING, 'due_date' => today()->addDays(90), 'line_id' => $line->id,
        ]);
        // Different line.
        $this->plannedWo($pt, 500, lineId: Line::factory()->create()->id);

        $report = $this->service->report($this->from, $this->to, $line->id);
        $row = $this->rowFor($report, $material->id);

        $this->assertEqualsWithDelta(100, $row['required_qty'], 0.001); // only the in-window, on-line WO
        $this->assertSame(1, $report['totals']['work_orders']);
    }

    public function test_api_endpoint_returns_report_for_admin(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $material = Material::factory()->create(['stock_quantity' => 0]);
        $pt = $this->productWithBom([['material' => $material, 'qty' => 2]]);
        $this->plannedWo($pt, 10);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/reports/net-requirements')
            ->assertOk()
            ->assertJsonPath('data.totals.work_orders', 1)
            ->assertJsonStructure(['data' => ['period', 'requirements', 'shortages', 'totals']]);
    }

    public function test_api_endpoint_is_forbidden_for_operator(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/reports/net-requirements')
            ->assertForbidden();
    }

    public function test_api_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/reports/net-requirements')->assertUnauthorized();
    }

    public function test_api_endpoint_rejects_invalid_filters(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/reports/net-requirements?line_id=999999&end_date=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['line_id', 'end_date']);
    }

    public function test_web_report_page_renders_for_admin_and_is_forbidden_for_operator(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($admin)->get('/admin/net-requirements')->assertOk();
        $this->actingAs($operator)->get('/admin/net-requirements')->assertForbidden();
    }

    public function test_web_report_rejects_invalid_filters(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get('/admin/net-requirements?date_from=2026-13-99')
            ->assertSessionHasErrors('date_from');
    }
}
