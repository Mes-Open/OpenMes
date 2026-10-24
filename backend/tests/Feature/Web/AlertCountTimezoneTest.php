<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\Admin\AlertController;
use App\Models\Issue;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\TimezoneRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The alert badge's "overdue" uses the plant's date, not UTC's.
 *
 * The server count (AlertController::totalCount → `nav.alertCount`, the badge's
 * fallback, and /admin/alerts/check) and the browser count (resources/js/lib/
 * alerts.js, which the badge, its hover panel and the Alerts page share) must
 * agree. Both are checked against tests/fixtures/alert-count-cases.json, so a
 * change to either side's rule breaks one of the two tests.
 */
class AlertCountTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TimezoneRegistry::flush();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        TimezoneRegistry::flush();
        date_default_timezone_set('UTC');
        config(['app.timezone' => 'UTC']);

        parent::tearDown();
    }

    /** Store the plant zone and freeze the clock at a UTC instant. */
    private function atPlantTime(string $timezone, string $utcInstant): void
    {
        TimezoneRegistry::save($timezone);
        TimezoneRegistry::apply();
        Carbon::setTestNow(Carbon::parse($utcInstant));
    }

    /** Orders and issues as the fixture describes them. */
    private function createRows(array $orders, array $issues): void
    {
        // Issues need an order; give them one that is neither overdue nor blocked.
        $host = WorkOrder::factory()->create(['due_date' => null, 'status' => WorkOrder::STATUS_PENDING]);

        foreach ($orders as $order) {
            WorkOrder::factory()->create($order);
        }

        foreach ($issues as $issue) {
            Issue::factory()->create($issue + ['work_order_id' => $host->id]);
        }
    }

    private function warsawBoundaryRows(): void
    {
        $this->createRows([
            ['due_date' => '2026-06-15 00:00:00', 'status' => WorkOrder::STATUS_IN_PROGRESS], // today
            ['due_date' => '2026-06-14 00:00:00', 'status' => WorkOrder::STATUS_PENDING],     // yesterday
            ['due_date' => '2026-06-14 00:00:00', 'status' => WorkOrder::STATUS_DONE],
            ['due_date' => null, 'status' => WorkOrder::STATUS_BLOCKED],
        ], [
            ['status' => Issue::STATUS_OPEN],
            ['status' => Issue::STATUS_ACKNOWLEDGED],
            ['status' => Issue::STATUS_RESOLVED],
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_order_due_today_is_not_overdue_at_half_past_midnight_in_warsaw(): void
    {
        // 22:30 UTC on 14 June = 00:30 on 15 June in Warsaw (CEST, UTC+2).
        $this->atPlantTime('Europe/Warsaw', '2026-06-14 22:30:00 UTC');
        $this->warsawBoundaryRows();

        // 2 open issues + 1 overdue (yesterday's) + 1 blocked.
        $this->assertSame(4, AlertController::totalCount());
    }

    public function test_the_day_boundary_moves_with_the_plant_timezone(): void
    {
        // Same instant, plant on UTC: it is still 14 June, nothing is overdue.
        $this->atPlantTime('UTC', '2026-06-14 22:30:00 UTC');
        $this->warsawBoundaryRows();

        $this->assertSame(3, AlertController::totalCount());
    }

    public function test_nav_alert_count_equals_the_alerts_check_total(): void
    {
        $this->atPlantTime('Europe/Warsaw', '2026-06-14 22:30:00 UTC');
        $this->warsawBoundaryRows();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/alerts/Index')
                ->where('nav.alertCount', 4)
                ->where('timezone', 'Europe/Warsaw'));

        $this->actingAs($admin)
            ->getJson('/admin/alerts/check')
            ->assertOk()
            ->assertJsonPath('total', 4);
    }

    public function test_soft_deleted_overdue_order_is_not_counted(): void
    {
        $this->atPlantTime('Europe/Warsaw', '2026-06-14 22:30:00 UTC');
        $order = WorkOrder::factory()->create(['due_date' => '2026-06-14 00:00:00', 'status' => WorkOrder::STATUS_PENDING]);
        $this->assertSame(1, AlertController::totalCount());

        $order->delete();

        $this->assertSoftDeleted($order);
        $this->assertSame(0, AlertController::totalCount());
    }

    public function test_server_count_matches_the_shared_fixture(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/fixtures/alert-count-cases.json')), true);

        foreach ($fixture['cases'] as $case) {
            WorkOrder::query()->forceDelete();
            Issue::query()->forceDelete();
            TimezoneRegistry::flush();

            $this->atPlantTime($case['timezone'], $case['now']);
            $this->createRows($case['orders'], $case['issues']);

            $this->assertSame($case['expected']['total'], AlertController::totalCount(), $case['name']);
        }
    }

    public function test_guest_is_redirected_from_alerts_check(): void
    {
        $this->get('/admin/alerts/check')->assertRedirect('/login');
    }

    public function test_operator_cannot_read_alerts_check(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get('/admin/alerts/check')->assertForbidden();
    }

    public function test_operator_nav_alert_count_is_zero(): void
    {
        $this->atPlantTime('Europe/Warsaw', '2026-06-14 22:30:00 UTC');
        $this->warsawBoundaryRows();
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->get('/operator/select-line')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('nav.alertCount', 0));
    }
}
