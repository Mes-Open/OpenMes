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
use Illuminate\Support\Facades\DB;
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
            // No frozen BOM: the report reads the selected or newest active
            // template. Orders made through WorkOrderService carry one.
            'process_snapshot' => null,
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
            'product_type_id' => $pt->id, 'planned_qty' => 999, 'process_snapshot' => null,
            'status' => WorkOrder::STATUS_PENDING, 'due_date' => today()->addDays(90), 'line_id' => $line->id,
        ]);
        // Different line.
        $this->plannedWo($pt, 500, lineId: Line::factory()->create()->id);

        $report = $this->service->report($this->from, $this->to, $line->id);
        $row = $this->rowFor($report, $material->id);

        $this->assertEqualsWithDelta(100, $row['required_qty'], 0.001); // only the in-window, on-line WO
        $this->assertSame(1, $report['totals']['work_orders']);
    }

    /**
     * Add another template for the product type with the given BOM lines.
     *
     * @param  array<int, array{material: Material, qty: float}>  $bom
     */
    private function templateFor(ProductType $pt, int $version, array $bom, bool $active = true): ProcessTemplate
    {
        $tpl = ProcessTemplate::factory()->create([
            'product_type_id' => $pt->id,
            'is_active' => $active,
            'version' => $version,
        ]);
        foreach ($bom as $line) {
            BomItem::factory()->create([
                'process_template_id' => $tpl->id,
                'material_id' => $line['material']->id,
                'quantity_per_unit' => $line['qty'],
                'scrap_percentage' => 0,
            ]);
        }

        return $tpl;
    }

    /**
     * The Telka chain: a finished frame (WG) made from a galvanised frame (DK),
     * made from a black frame (CZ, purchased here to keep it short). DK is both a
     * product type (it has its own orders) and the material WG consumes.
     *
     * @return array{wg: ProductType, dk: ProductType, dkMaterial: Material, cz: Material}
     */
    private function frameChain(): array
    {
        $cz = Material::factory()->create(['code' => 'CZ', 'stock_quantity' => 0]);
        $dk = ProductType::factory()->create(['code' => 'DK']);
        $dkTemplate = $this->templateFor($dk, 1, [['material' => $cz, 'qty' => 1]]);
        $dkMaterial = Material::factory()->manufactured($dkTemplate->id)->create(['code' => 'DK', 'stock_quantity' => 0]);
        $wg = ProductType::factory()->create(['code' => 'WG']);
        $this->templateFor($wg, 1, [['material' => $dkMaterial, 'qty' => 1]]);

        return ['wg' => $wg, 'dk' => $dk, 'dkMaterial' => $dkMaterial, 'cz' => $cz];
    }

    public function test_by_default_open_orders_for_a_semi_finished_part_are_not_supply(): void
    {
        $c = $this->frameChain();
        $this->plannedWo($c['wg'], 200);
        $this->plannedWo($c['dk'], 200);

        $report = $this->service->report($this->from, $this->to);

        // 200 through WG → DK → CZ, plus 200 for the DK order itself.
        $this->assertEqualsWithDelta(400, $this->rowFor($report, $c['cz']->id)['net_qty'], 0.001);
        $this->assertEqualsWithDelta(0, $this->rowFor($report, $c['dkMaterial']->id)['on_order_qty'], 0.001);
    }

    public function test_open_orders_for_a_semi_finished_part_count_as_supply_when_asked(): void
    {
        $c = $this->frameChain();
        $this->plannedWo($c['wg'], 200);
        $this->plannedWo($c['dk'], 200);

        $report = $this->service->report($this->from, $this->to, includeOpenOrders: true);

        $dkRow = $this->rowFor($report, $c['dkMaterial']->id);
        $this->assertEqualsWithDelta(200, $dkRow['required_qty'], 0.001);
        $this->assertEqualsWithDelta(200, $dkRow['on_order_qty'], 0.001);
        $this->assertEqualsWithDelta(0, $dkRow['net_qty'], 0.001);
        $this->assertFalse($dkRow['is_short']);
        // Only the DK order itself still needs black frames.
        $this->assertEqualsWithDelta(200, $this->rowFor($report, $c['cz']->id)['net_qty'], 0.001);
    }

    public function test_open_supply_covers_only_what_it_can_and_the_rest_explodes(): void
    {
        $c = $this->frameChain();
        $this->plannedWo($c['wg'], 200);
        $this->plannedWo($c['dk'], 150, status: WorkOrder::STATUS_IN_PROGRESS);

        $report = $this->service->report($this->from, $this->to, includeOpenOrders: true);

        $this->assertEqualsWithDelta(50, $this->rowFor($report, $c['dkMaterial']->id)['net_qty'], 0.001);
        // The in-progress DK order adds no demand; only the 50 uncovered DK explode.
        $this->assertEqualsWithDelta(50, $this->rowFor($report, $c['cz']->id)['net_qty'], 0.001);
    }

    public function test_finished_cancelled_and_late_orders_are_not_supply(): void
    {
        $c = $this->frameChain();
        $this->plannedWo($c['wg'], 100);
        $this->plannedWo($c['dk'], 100, status: WorkOrder::STATUS_DONE);
        $this->plannedWo($c['dk'], 100, status: WorkOrder::STATUS_CANCELLED);
        WorkOrder::factory()->create([
            'product_type_id' => $c['dk']->id, 'planned_qty' => 100, 'process_snapshot' => null,
            'status' => WorkOrder::STATUS_IN_PROGRESS, 'due_date' => today()->addDays(90),
            'line_id' => Line::factory()->create()->id,
        ]);

        $report = $this->service->report($this->from, $this->to, includeOpenOrders: true);

        $this->assertEqualsWithDelta(0, $this->rowFor($report, $c['dkMaterial']->id)['on_order_qty'], 0.001);
        $this->assertEqualsWithDelta(100, $this->rowFor($report, $c['dkMaterial']->id)['net_qty'], 0.001);
    }

    public function test_quantity_already_received_from_an_open_order_is_not_counted_twice(): void
    {
        $c = $this->frameChain();
        $c['dkMaterial']->update(['stock_quantity' => 40]); // received from the order below
        $this->plannedWo($c['wg'], 200);
        $dkOrder = $this->plannedWo($c['dk'], 100, status: WorkOrder::STATUS_IN_PROGRESS);

        $receipt = \App\Models\StockDocument::create([
            'type' => \App\Models\StockDocument::TYPE_MATERIAL_RECEIPT,
            'document_no' => 'PW-TEST-1',
            'status' => \App\Models\StockDocument::STATUS_POSTED,
            'work_order_id' => $dkOrder->id,
            'warehouse_id' => \App\Models\Warehouse::factory()->rawMaterial()->create()->id,
        ]);
        $receipt->lines()->create(['material_id' => $c['dkMaterial']->id, 'quantity' => 40, 'sort_order' => 1]);

        $report = $this->service->report($this->from, $this->to, includeOpenOrders: true);
        $dkRow = $this->rowFor($report, $c['dkMaterial']->id);

        $this->assertEqualsWithDelta(60, $dkRow['on_order_qty'], 0.001); // 100 − 40 already in stock
        $this->assertEqualsWithDelta(100, $dkRow['net_qty'], 0.001);     // 200 − 40 − 60
    }

    public function test_api_accepts_the_open_orders_switch(): void
    {
        $c = $this->frameChain();
        $this->plannedWo($c['wg'], 200);
        $this->plannedWo($c['dk'], 200);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/reports/net-requirements?include_open_orders=1')
            ->assertOk()
            ->assertJsonPath('data.include_open_orders', true);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/reports/net-requirements?include_open_orders=maybe')
            ->assertStatus(422)
            ->assertJsonValidationErrors('include_open_orders');
    }

    public function test_web_report_passes_the_open_orders_switch_and_rejects_a_bad_value(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get('/admin/net-requirements?include_open_orders=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('includeOpenOrders', true));

        $this->actingAs($admin)
            ->get('/admin/net-requirements?include_open_orders=maybe')
            ->assertSessionHasErrors('include_open_orders');
    }

    public function test_an_order_is_measured_by_its_frozen_bom_not_the_template_edited_since(): void
    {
        $frozenPart = Material::factory()->create(['stock_quantity' => 0]);
        $editedInPart = Material::factory()->create(['stock_quantity' => 0]);
        $pt = $this->productWithBom([['material' => $editedInPart, 'qty' => 5]]);

        $wo = $this->plannedWo($pt, 10);
        // What the order was released with: 2 per unit + 10% scrap, plus a sub-assembly reference.
        $wo->update(['process_snapshot' => ['steps' => [], 'bom' => [
            ['component_kind' => 'material', 'material_id' => $frozenPart->id, 'quantity_per_unit' => 2, 'scrap_percentage' => 10],
            ['component_kind' => 'product_type', 'material_id' => null, 'product_type_id' => $pt->id, 'quantity_per_unit' => 1, 'scrap_percentage' => 0],
        ]]]);

        $report = $this->service->report($this->from, $this->to);

        $this->assertEqualsWithDelta(22, $this->rowFor($report, $frozenPart->id)['required_qty'], 0.001); // 2 × 1.1 × 10
        $this->assertNull($this->rowFor($report, $editedInPart->id));
    }

    public function test_an_open_order_packed_on_pallets_is_not_supply_of_the_material(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'pallet_stock_documents'], ['value' => json_encode('draft')]);
        $c = $this->frameChain();
        $this->plannedWo($c['wg'], 100);
        $dkOrder = $this->plannedWo($c['dk'], 100, status: WorkOrder::STATUS_IN_PROGRESS);
        // Its routing ends on a packing step: the pallets will receive it as a product.
        $dkOrder->update(['process_snapshot' => ['steps' => [['step_number' => 1, 'kind' => \App\Models\TemplateStep::KIND_PACKING]], 'bom' => []]]);

        $report = $this->service->report($this->from, $this->to, includeOpenOrders: true);

        $this->assertEqualsWithDelta(0, $this->rowFor($report, $c['dkMaterial']->id)['on_order_qty'], 0.001);
        $this->assertEqualsWithDelta(100, $this->rowFor($report, $c['dkMaterial']->id)['net_qty'], 0.001);
    }

    public function test_an_order_created_with_a_selected_bom_is_measured_by_it(): void
    {
        $selectedPart = Material::factory()->create(['stock_quantity' => 0]);
        $newestPart = Material::factory()->create(['stock_quantity' => 0]);
        $pt = ProductType::factory()->create();
        $selected = $this->templateFor($pt, 1, [['material' => $selectedPart, 'qty' => 2]]);
        $this->templateFor($pt, 2, [['material' => $newestPart, 'qty' => 3]]);

        app(\App\Services\WorkOrder\WorkOrderService::class)->createWorkOrder([
            'order_no' => 'WO-MRP-SNAP',
            'product_type_id' => $pt->id,
            'line_id' => Line::factory()->create()->id,
            'planned_qty' => 100,
            'due_date' => $this->due->toDateString(),
            'bom_template_ids' => [$selected->id],
        ]);

        $report = $this->service->report($this->from, $this->to);

        $this->assertEqualsWithDelta(200, $this->rowFor($report, $selectedPart->id)['required_qty'], 0.001);
        $this->assertNull($this->rowFor($report, $newestPart->id));
    }

    public function test_uses_the_bom_selected_on_the_work_order_instead_of_the_newest(): void
    {
        $selectedPart = Material::factory()->create(['stock_quantity' => 0]);
        $newestPart = Material::factory()->create(['stock_quantity' => 0]);
        $pt = ProductType::factory()->create();
        $selected = $this->templateFor($pt, 1, [['material' => $selectedPart, 'qty' => 2]]);
        $this->templateFor($pt, 2, [['material' => $newestPart, 'qty' => 3]]);

        $wo = $this->plannedWo($pt, 100);
        $wo->bomTemplates()->attach($selected->id, ['sort_order' => 0]);

        $report = $this->service->report($this->from, $this->to);

        $this->assertEqualsWithDelta(200, $this->rowFor($report, $selectedPart->id)['required_qty'], 0.001);
        $this->assertNull($this->rowFor($report, $newestPart->id));
    }

    public function test_sums_every_bom_selected_on_the_work_order(): void
    {
        $shared = Material::factory()->create(['stock_quantity' => 0]);
        $onlyFirst = Material::factory()->create(['stock_quantity' => 0]);
        $onlySecond = Material::factory()->create(['stock_quantity' => 0]);
        $pt = ProductType::factory()->create();
        $first = $this->templateFor($pt, 1, [
            ['material' => $shared, 'qty' => 1],
            ['material' => $onlyFirst, 'qty' => 2],
        ]);
        $second = $this->templateFor($pt, 2, [
            ['material' => $shared, 'qty' => 4],
            ['material' => $onlySecond, 'qty' => 5],
        ]);

        $wo = $this->plannedWo($pt, 10);
        $wo->bomTemplates()->attach([
            $first->id => ['sort_order' => 0],
            $second->id => ['sort_order' => 1],
        ]);

        $report = $this->service->report($this->from, $this->to);

        $this->assertEqualsWithDelta(50, $this->rowFor($report, $shared->id)['required_qty'], 0.001); // (1 + 4) × 10
        $this->assertEqualsWithDelta(20, $this->rowFor($report, $onlyFirst->id)['required_qty'], 0.001);
        $this->assertEqualsWithDelta(50, $this->rowFor($report, $onlySecond->id)['required_qty'], 0.001);
    }

    public function test_selected_bom_counts_even_when_the_template_is_no_longer_active(): void
    {
        $oldPart = Material::factory()->create(['stock_quantity' => 0]);
        $newPart = Material::factory()->create(['stock_quantity' => 0]);
        $pt = ProductType::factory()->create();
        $old = $this->templateFor($pt, 1, [['material' => $oldPart, 'qty' => 1]], active: false);
        $this->templateFor($pt, 2, [['material' => $newPart, 'qty' => 1]]);

        // The order was planned on v1; the shop floor will consume v1's materials.
        $wo = $this->plannedWo($pt, 30);
        $wo->bomTemplates()->attach($old->id, ['sort_order' => 0]);

        $report = $this->service->report($this->from, $this->to);

        $this->assertEqualsWithDelta(30, $this->rowFor($report, $oldPart->id)['required_qty'], 0.001);
        $this->assertNull($this->rowFor($report, $newPart->id));
    }

    public function test_work_order_without_a_selected_bom_uses_the_newest_active_template(): void
    {
        $oldPart = Material::factory()->create(['stock_quantity' => 0]);
        $newPart = Material::factory()->create(['stock_quantity' => 0]);
        $pt = ProductType::factory()->create();
        $this->templateFor($pt, 1, [['material' => $oldPart, 'qty' => 1]]);
        $this->templateFor($pt, 2, [['material' => $newPart, 'qty' => 1]]);

        $this->plannedWo($pt, 40);

        $report = $this->service->report($this->from, $this->to);

        $this->assertEqualsWithDelta(40, $this->rowFor($report, $newPart->id)['required_qty'], 0.001);
        $this->assertNull($this->rowFor($report, $oldPart->id));
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
