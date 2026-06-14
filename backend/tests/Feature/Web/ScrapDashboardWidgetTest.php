<?php

namespace Tests\Feature\Web;

use App\Models\DashboardWidget;
use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ScrapDashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function reason(string $code = 'SURF-DEF'): ScrapReason
    {
        return ScrapReason::create([
            'code' => $code,
            'name' => 'Surface defect',
            'category' => ScrapReason::CATEGORY_MATERIAL,
            'is_active' => true,
        ]);
    }

    private function logScrap(int $reasonId, float $qty, ?string $reportedAt = null): ScrapEntry
    {
        return ScrapEntry::create([
            'work_order_id' => WorkOrder::factory()->create()->id,
            'scrap_reason_id' => $reasonId,
            'quantity' => $qty,
            'reported_by' => $this->admin->id,
            'reported_at' => $reportedAt ?? now(),
        ]);
    }

    public function test_widget_seeded_by_migration_and_enabled_by_default(): void
    {
        $w = DashboardWidget::firstWhere('widget_id', 'scrap_overview');

        $this->assertNotNull($w);
        $this->assertTrue($w->enabled);
        $this->assertSame('main', $w->zone);
    }

    public function test_widget_totals_scrap_over_last_30_days(): void
    {
        $reason = $this->reason();
        $this->logScrap($reason->id, 5);
        $this->logScrap($reason->id, 3);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('enabledWidgets', fn ($w) => collect($w)->contains('scrap_overview'))
                ->where('scrapStats.total_qty_30d', fn ($v) => abs((float) $v - 8.0) < 0.001)
                ->where('scrapStats.entries_30d', 2)
                ->where('scrapStats.top_reason', 'Surface defect')
                ->where('scrapStats.top_reason_qty', fn ($v) => abs((float) $v - 8.0) < 0.001)
            );
    }

    public function test_widget_excludes_scrap_older_than_30_days(): void
    {
        $reason = $this->reason();
        $this->logScrap($reason->id, 5);                              // in window
        $this->logScrap($reason->id, 99, now()->subDays(45)->toDateTimeString()); // out of window

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('scrapStats.total_qty_30d', fn ($v) => abs((float) $v - 5.0) < 0.001)
                ->where('scrapStats.entries_30d', 1)
            );
    }

    public function test_widget_handles_no_scrap(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('scrapStats.total_qty_30d', fn ($v) => abs((float) $v) < 0.001)
                ->where('scrapStats.entries_30d', 0)
                ->where('scrapStats.top_reason', null)
            );
    }

    public function test_stats_null_when_widget_disabled(): void
    {
        DashboardWidget::where('widget_id', 'scrap_overview')->update(['enabled' => false]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('scrapStats', null));
    }
}
