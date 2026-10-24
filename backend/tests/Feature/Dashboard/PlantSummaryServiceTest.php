<?php

namespace Tests\Feature\Dashboard;

use App\Enums\IssueDisposition;
use App\Models\Issue;
use App\Models\Line;
use App\Models\Material;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Dashboard\PlantSummaryService;
use App\Support\ModuleRegistry;
use App\Support\TimezoneRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The plant-overview summary strip: work orders today (plant day), materials
 * below minimum, open non-conformances and the next order.
 */
class PlantSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlantSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));
        $this->service = app(PlantSummaryService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        TimezoneRegistry::flush();
        date_default_timezone_set('UTC');
        config(['app.timezone' => 'UTC']);
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function summary(): array
    {
        return $this->service->summary(Carbon::now());
    }

    private function order(string $status, ?Carbon $plannedStart, array $attributes = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'status' => $status,
            'planned_start_at' => $plannedStart,
        ], $attributes));
    }

    // ── work orders today ──────────────────────────────────────────────

    public function test_running_counts_every_in_progress_order(): void
    {
        $this->order(WorkOrder::STATUS_IN_PROGRESS, Carbon::parse('2026-05-20 08:00'));
        $this->order(WorkOrder::STATUS_IN_PROGRESS, null);
        $this->order(WorkOrder::STATUS_BLOCKED, Carbon::parse('2026-05-26 08:00'));
        $this->order(WorkOrder::STATUS_DONE, Carbon::parse('2026-05-26 08:00'));

        $this->assertSame(2, $this->summary()['work_orders']['running']);
    }

    public function test_pending_counts_orders_planned_to_start_on_the_plant_day(): void
    {
        TimezoneRegistry::save('Europe/Warsaw');
        TimezoneRegistry::refresh();
        // 23:00 in Warsaw is 21:00 UTC, still the 26th in both.
        Carbon::setTestNow(Carbon::parse('2026-05-26 23:00:00', 'Europe/Warsaw'));

        $warsaw = fn (string $at) => Carbon::parse($at, 'Europe/Warsaw');

        // Plant today: 01:00 and 01:45 on the 26th are still the 25th in UTC — count.
        $this->order(WorkOrder::STATUS_PENDING, $warsaw('2026-05-26 01:00'));
        $this->order(WorkOrder::STATUS_PENDING, $warsaw('2026-05-26 01:45'));
        $this->order(WorkOrder::STATUS_ACCEPTED, $warsaw('2026-05-26 23:30'));
        // Plant tomorrow: 00:30 on the 27th is still the 26th in UTC — does not count.
        $this->order(WorkOrder::STATUS_PENDING, $warsaw('2026-05-27 00:30'));
        // Plant yesterday.
        $this->order(WorkOrder::STATUS_PENDING, $warsaw('2026-05-25 23:30'));
        // Not pending.
        $this->order(WorkOrder::STATUS_IN_PROGRESS, $warsaw('2026-05-26 09:00'));

        // A UTC day would give 2 (23:30 on the 26th and 00:30 on the 27th).
        $this->assertSame(3, $this->summary()['work_orders']['pending']);
    }

    // ── materials ──────────────────────────────────────────────────────

    public function test_materials_below_minimum_matches_the_widget_predicate(): void
    {
        Material::factory()->create(['stock_quantity' => 2, 'min_stock_level' => 10]);
        Material::factory()->create(['stock_quantity' => 10, 'min_stock_level' => 10]); // at minimum counts
        Material::factory()->create(['stock_quantity' => 50, 'min_stock_level' => 10]);
        Material::factory()->inactive()->create(['stock_quantity' => 0, 'min_stock_level' => 10]);
        Material::factory()->create(['stock_quantity' => 0, 'min_stock_level' => null]);

        $this->assertSame(['below_min' => 2], $this->summary()['materials']);
    }

    public function test_materials_field_is_null_when_the_module_is_off(): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['materials'])));

        $summary = $this->summary();

        $this->assertNull($summary['materials']);
        $this->assertNotNull($summary['non_conformances']);
    }

    // ── non-conformances ───────────────────────────────────────────────

    public function test_only_open_issues_flagged_as_non_conformances_are_counted(): void
    {
        Issue::factory()->create(); // plain production issue — not an NC
        Issue::factory()->create(['source' => Issue::SOURCE_IN_PROCESS]);
        Issue::factory()->create(['nc_source' => Issue::NC_SOURCE_SUPPLIER]);
        Issue::factory()->create(['non_conforming_qty' => 3]);
        Issue::factory()->scrap()->create(['status' => Issue::STATUS_ACKNOWLEDGED]);
        Issue::factory()->create(['source' => Issue::SOURCE_CUSTOMER_COMPLAINT, 'status' => Issue::STATUS_RESOLVED]);
        Issue::factory()->create(['non_conforming_qty' => 0, 'disposition' => IssueDisposition::Pending->value]);

        $this->assertSame(['open' => 4], $this->summary()['non_conformances']);
    }

    public function test_non_conformances_field_is_null_when_quality_is_off(): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['quality'])));

        $summary = $this->summary();

        $this->assertNull($summary['non_conformances']);
        $this->assertNotNull($summary['materials']);
    }

    public function test_non_conformances_are_tenant_isolated(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        $orderA = WorkOrder::factory()->create(['tenant_id' => $tenantA->id]);
        $orderB = WorkOrder::factory()->create(['tenant_id' => $tenantB->id]);
        Issue::factory()->create(['work_order_id' => $orderA->id, 'source' => Issue::SOURCE_IN_PROCESS]);
        Issue::factory()->create(['work_order_id' => $orderB->id, 'source' => Issue::SOURCE_IN_PROCESS]);
        // Unattached: nobody's tenant can claim it.
        Issue::factory()->create(['work_order_id' => null, 'source' => Issue::SOURCE_IN_PROCESS]);

        $this->actingAs($userA);

        $this->assertSame(['open' => 1], $this->summary()['non_conformances']);
    }

    // ── next order ─────────────────────────────────────────────────────

    public function test_next_order_is_the_nearest_future_planned_start(): void
    {
        $line = Line::factory()->create(['name' => 'L1 CNC']);
        $product = ProductType::factory()->create(['code' => 'AL-220']);

        $this->order(WorkOrder::STATUS_PENDING, Carbon::parse('2026-05-26 09:00')); // past
        $this->order(WorkOrder::STATUS_IN_PROGRESS, Carbon::parse('2026-05-26 10:30')); // already running
        $this->order(WorkOrder::STATUS_CANCELLED, Carbon::parse('2026-05-26 10:20'));
        $this->order(WorkOrder::STATUS_ACCEPTED, Carbon::parse('2026-05-27 06:00'));
        $next = $this->order(WorkOrder::STATUS_PENDING, Carbon::parse('2026-05-26 13:10'), [
            'line_id' => $line->id, 'product_type_id' => $product->id,
        ]);

        $this->assertSame([
            'id' => $next->id,
            'order_no' => $next->order_no,
            'planned_start_at' => Carbon::parse('2026-05-26 13:10')->toIso8601String(),
            'line_name' => 'L1 CNC',
            'product_code' => 'AL-220',
        ], $this->summary()['next_order']);
    }

    public function test_no_upcoming_order_is_null(): void
    {
        $this->order(WorkOrder::STATUS_PENDING, Carbon::parse('2026-05-26 09:00'));
        $this->order(WorkOrder::STATUS_PENDING, null);

        $this->assertNull($this->summary()['next_order']);
    }
}
