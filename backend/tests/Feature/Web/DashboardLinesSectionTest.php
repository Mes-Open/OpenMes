<?php

namespace Tests\Feature\Web;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ModuleRegistry;
use App\Support\TabRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Delivery of the plant overview's "Lines" cards (`overview.line_orders`) and
 * summary strip (`overview.summary`) on the admin dashboard.
 */
class DashboardLinesSectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_carries_line_orders_and_summary(): void
    {
        $line = Line::factory()->create(['name' => 'L1 CNC']);
        $product = ProductType::factory()->create(['code' => 'AL-220']);
        $order = WorkOrder::factory()->create([
            'line_id' => $line->id, 'product_type_id' => $product->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'planned_qty' => 450, 'produced_qty' => 312,
            'planned_end_at' => Carbon::parse('2026-05-26 13:00'),
        ]);
        $idle = Line::factory()->create(['name' => 'L2 Assembly']);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->has('overview.line_orders', 2)
                ->where('overview.line_orders', fn ($rows) => collect($rows)->firstWhere('line_id', $line->id)['order']['id'] === $order->id
                    && collect($rows)->firstWhere('line_id', $line->id)['order']['percent'] === 69
                    && collect($rows)->firstWhere('line_id', $line->id)['order']['product_code'] === 'AL-220'
                    && collect($rows)->firstWhere('line_id', $idle->id)['order'] === null)
                ->where('overview.summary.work_orders.running', 1)
                ->has('overview.summary.materials.below_min')
                ->has('overview.summary.non_conformances.open')
                ->where('overview.summary.next_order', null));
    }

    public function test_partial_reload_of_overview_includes_the_new_keys(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->reloadOnly('overview', fn (AssertableInertia $reload) => $reload
                    ->has('overview.line_orders')
                    ->has('overview.summary.work_orders')
                    ->missing('materialsStats')));
    }

    public function test_disabled_modules_drop_their_summary_fields(): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['materials', 'quality'])));

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('overview.summary.materials', null)
                ->where('overview.summary.non_conformances', null)
                ->has('overview.summary.work_orders'));
    }

    public function test_line_orders_are_tenant_isolated(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('Admin');

        $lineA = Line::factory()->create(['tenant_id' => $tenantA->id]);
        $lineB = Line::factory()->create(['tenant_id' => $tenantB->id]);
        WorkOrder::factory()->create(['line_id' => $lineA->id, 'status' => WorkOrder::STATUS_IN_PROGRESS, 'tenant_id' => $tenantA->id]);
        WorkOrder::factory()->create(['line_id' => $lineB->id, 'status' => WorkOrder::STATUS_IN_PROGRESS, 'tenant_id' => $tenantB->id]);

        $this->actingAs($adminA)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('overview.line_orders', fn ($rows) => collect($rows)->pluck('line_id')->all() === [$lineA->id])
                ->where('overview.summary.work_orders.running', 1));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_dashboard_tab_cannot_open_it(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor'); // holds tab:orders, not tab:dashboard

        $this->actingAs($supervisor)
            ->get(route('admin.dashboard'))
            ->assertRedirect(TabRegistry::firstAccessibleUrl($supervisor));
    }
}
