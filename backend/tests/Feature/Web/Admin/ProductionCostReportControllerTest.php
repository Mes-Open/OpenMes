<?php

namespace Tests\Feature\Web\Admin;

use App\Models\AdditionalCost;
use App\Models\EmployeeActivity;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionCostReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /** A finished work order costed at 10 (material) + 80 (labor) + 25 (additional) = 115. */
    private function costedWorkOrder(): WorkOrder
    {
        $wo = WorkOrder::factory()->done()->create(['planned_qty' => 10, 'produced_qty' => 10]);

        $material = Material::factory()->create(['unit_price' => 2]);
        MaterialAllocation::factory()->consumed(5, snapshotPrice: 2)->create([
            'work_order_id' => $wo->id,
            'material_id' => $material->id,
        ]); // 10

        $worker = Worker::factory()->paidHourly(40)->create();
        EmployeeActivity::factory()->hours(2)->create([
            'worker_id' => $worker->id,
            'work_order_id' => $wo->id,
            'type' => 'work',
        ]); // 80

        AdditionalCost::factory()->create(['work_order_id' => $wo->id, 'amount' => 25]); // 25

        return $wo;
    }

    public function test_index_renders_with_cost_summary(): void
    {
        $this->costedWorkOrder();

        $response = $this->actingAs($this->admin)->get(route('admin.cost-reports.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/cost-reports/Index')
            ->where('summary.material_cost', 10)
            ->where('summary.labor_cost', 80)
            ->where('summary.additional_cost', 25)
            ->where('summary.total_cost', 115)
            ->has('orders.data', 1)
        );
    }

    public function test_show_renders_full_breakdown(): void
    {
        $wo = $this->costedWorkOrder();

        $response = $this->actingAs($this->admin)->get(route('admin.cost-reports.show', $wo));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/cost-reports/Show')
            ->where('breakdown.total_cost', 115)
            ->where('breakdown.cost_per_unit', 11.5)
            ->has('breakdown.materials.items', 1)
            ->has('breakdown.labor.items', 1)
            ->has('breakdown.additional.items', 1)
        );
    }

    public function test_export_returns_csv(): void
    {
        $this->costedWorkOrder();

        $response = $this->actingAs($this->admin)->get(route('admin.cost-reports.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $response->assertSee('Total cost');
    }

    public function test_invalid_custom_date_range_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cost-reports.index', [
            'preset' => 'custom',
            'from' => '2026-06-10',
            'to' => '2026-06-01',
        ]));

        $response->assertSessionHasErrors('to');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.cost-reports.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get(route('admin.cost-reports.index'))->assertForbidden();
    }
}
