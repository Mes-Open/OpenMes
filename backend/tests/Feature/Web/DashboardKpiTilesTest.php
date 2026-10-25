<?php

namespace Tests\Feature\Web;

use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\QualityCheck;
use App\Models\ScrapEntry;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Quality\FirstPassYieldService;
use App\Services\Schedule\SchedulePlannerService;
use App\Services\Scrap\ScrapReportService;
use App\Support\ModuleRegistry;
use App\Support\ProductionTargetSettings;
use App\Support\TabRegistry;
use App\Support\TimezoneRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The "shift at a glance" tiles of the plant overview (`overview.kpis`):
 * orders not scheduled, lines producing, scrap rate and first-pass yield.
 */
class DashboardKpiTilesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // A Tuesday, mid-morning, inside the 06:00–14:00 shift.
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        Shift::create([
            'name' => 'Shift A', 'code' => 'A',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => null, 'is_active' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-05-26 '.$time);
    }

    /** @return array<string, mixed> */
    private function kpis(?User $user = null): array
    {
        return $this->actingAs($user ?? $this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->inertiaProps('overview.kpis');
    }

    private function order(array $attributes = []): WorkOrder
    {
        return WorkOrder::factory()->create($attributes + ['due_date' => null]);
    }

    // ── orders not scheduled ───────────────────────────────────────────

    public function test_backlog_count_matches_the_planner_backlog(): void
    {
        $line = Line::factory()->create();

        $this->order(['line_id' => null, 'due_date' => '2026-05-28']);
        $this->order(['line_id' => $line->id]); // on a line, nothing places it in time
        $this->order(['line_id' => null, 'status' => WorkOrder::STATUS_BLOCKED]);
        // Not in the backlog: scheduled, done, cancelled.
        $this->order(['line_id' => $line->id, 'due_date' => '2026-05-28']);
        $this->order(['line_id' => null, 'status' => WorkOrder::STATUS_DONE]);
        $this->order(['line_id' => null, 'status' => WorkOrder::STATUS_CANCELLED]);

        $planner = count(app(SchedulePlannerService::class)->board()['backlogOrders']);
        $this->assertSame(3, $planner);
        $this->assertSame($planner, $this->kpis()['backlog']['count']);

        // And over HTTP: the planner page's backlog prop is the same size.
        $this->actingAs($this->admin)
            ->get(route('admin.schedule'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('backlogOrders', $planner));
    }

    public function test_backlog_splits_due_this_week_and_overdue(): void
    {
        $this->order(['line_id' => null, 'due_date' => '2026-05-25']);          // yesterday — overdue
        $this->order(['line_id' => null, 'due_date' => '2026-05-26 00:00:00']); // today
        $this->order(['line_id' => null, 'due_date' => '2026-05-31 00:00:00']); // Sunday this week
        $this->order(['line_id' => null, 'due_date' => '2026-06-01 00:00:00']); // next Monday — neither
        $this->order(['line_id' => null, 'due_date' => null]);                  // undated — neither

        $backlog = $this->kpis()['backlog'];

        $this->assertSame(5, $backlog['count']);
        $this->assertSame(1, $backlog['overdue']);
        $this->assertSame(2, $backlog['due_this_week']);
        $this->assertSame('bad', $backlog['tone']);
    }

    public function test_due_dates_are_read_in_the_plant_timezone(): void
    {
        TimezoneRegistry::save('Europe/Warsaw');
        TimezoneRegistry::apply();
        // 00:30 in Warsaw on Wednesday is still Tuesday in UTC.
        Carbon::setTestNow(Carbon::parse('2026-05-27 00:30:00', 'Europe/Warsaw'));

        $this->order(['line_id' => null, 'due_date' => Carbon::parse('2026-05-26 00:00:00', 'Europe/Warsaw')]);
        $this->order(['line_id' => null, 'due_date' => Carbon::parse('2026-05-27 00:00:00', 'Europe/Warsaw')]);

        $backlog = $this->kpis()['backlog'];

        $this->assertSame(1, $backlog['overdue']);
        $this->assertSame(1, $backlog['due_this_week']);
    }

    public function test_empty_backlog_is_ok(): void
    {
        $this->order(['line_id' => Line::factory()->create()->id, 'due_date' => '2026-05-28']);

        $backlog = $this->kpis()['backlog'];

        $this->assertSame(0, $backlog['count']);
        $this->assertSame('ok', $backlog['tone']);
    }

    public function test_only_undated_backlog_is_muted(): void
    {
        $this->order(['line_id' => null]);

        $this->assertSame('mute', $this->kpis()['backlog']['tone']);
    }

    // ── lines producing ────────────────────────────────────────────────

    private function lineIn(?string $machineState): Line
    {
        $line = Line::factory()->create(['is_active' => true]);
        if ($machineState) {
            $station = Workstation::factory()->create(['line_id' => $line->id]);
            WorkstationState::create([
                'workstation_id' => $station->id, 'state' => $machineState,
                'started_at' => $this->at('08:00'), 'ended_at' => null,
            ]);
        }

        return $line;
    }

    public function test_line_state_counters(): void
    {
        $this->lineIn(WorkstationState::RUNNING);
        $this->lineIn(WorkstationState::RUNNING);
        $this->lineIn(WorkstationState::FAULT);
        // A line without a collector, down on a stop reported by hand.
        $manual = $this->lineIn(null);
        ProductionDowntime::create([
            'line_id' => $manual->id,
            'downtime_reason_id' => DowntimeReason::factory()->unplanned()->create()->id,
            'started_at' => $this->at('09:00'),
        ]);
        $blocked = $this->lineIn(null);
        $this->order(['line_id' => $blocked->id, 'status' => WorkOrder::STATUS_BLOCKED]);
        $this->lineIn(null); // idle
        Line::factory()->create(['is_active' => false]); // not counted

        $lines = $this->kpis()['lines'];

        $this->assertSame(6, $lines['total']);
        $this->assertSame(2, $lines['running']);
        $this->assertSame(2, $lines['down']);
        $this->assertSame(1, $lines['blocked']);
        $this->assertSame(1, $lines['idle']);
        $this->assertSame('bad', $lines['tone']);
    }

    public function test_blocked_without_down_is_a_warning(): void
    {
        $this->lineIn(WorkstationState::RUNNING);
        $this->lineIn(WorkstationState::WAITING);

        $this->assertSame('warn', $this->kpis()['lines']['tone']);
    }

    public function test_no_active_lines_is_muted_not_zero_of_zero_running(): void
    {
        $lines = $this->kpis()['lines'];

        $this->assertSame(0, $lines['total']);
        $this->assertSame('mute', $lines['tone']);
    }

    // ── scrap rate ─────────────────────────────────────────────────────

    private function produced(Line $line, float $qty, string $at): WorkOrder
    {
        $order = $this->order(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        Batch::factory()->create([
            'work_order_id' => $order->id, 'status' => Batch::STATUS_DONE,
            'produced_qty' => $qty, 'completed_at' => $this->at($at),
        ]);

        return $order;
    }

    private function scrap(WorkOrder $order, float $qty, Carbon $at): void
    {
        ScrapEntry::factory()->create(['work_order_id' => $order->id, 'quantity' => $qty, 'reported_at' => $at]);
    }

    public function test_scrap_rate_counts_only_the_shift_window(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $order = $this->produced($line, 200, '09:00');
        $this->scrap($order, 6, $this->at('08:30'));
        $this->scrap($order, 50, $this->at('05:00')); // before the shift
        $this->scrap($order, 50, Carbon::parse('2026-05-25 10:00')); // yesterday

        $scrap = $this->kpis()['scrap'];

        $this->assertSame(6.0, (float) $scrap['scrap_qty']);
        $this->assertSame(200.0, (float) $scrap['produced_qty']);
        $this->assertSame(3.0, (float) $scrap['rate_pct']);

        // Same maths as the Scrap Reports page for the same window.
        $report = app(ScrapReportService::class)->ratePerLine($this->at('06:00'), $this->at('14:00'));
        $this->assertSame((float) $report[0]['scrap_rate_pct'], (float) $scrap['rate_pct']);
    }

    public function test_scrap_on_an_inactive_line_is_left_out(): void
    {
        $active = Line::factory()->create(['is_active' => true]);
        $inactive = Line::factory()->create(['is_active' => false]);
        $this->scrap($this->produced($active, 100, '09:00'), 1, $this->at('09:00'));
        $this->scrap($this->produced($inactive, 100, '09:00'), 40, $this->at('09:00'));

        $this->assertSame(1.0, (float) $this->kpis()['scrap']['rate_pct']);
    }

    public function test_no_output_gives_no_rate_rather_than_zero(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $this->scrap($this->order(['line_id' => $line->id]), 3, $this->at('09:00'));

        $scrap = $this->kpis()['scrap'];

        $this->assertNull($scrap['rate_pct']);
        $this->assertSame('mute', $scrap['tone']);
    }

    // ── targets ────────────────────────────────────────────────────────

    public function test_targets_default_when_nothing_is_saved(): void
    {
        $kpis = $this->kpis();

        $this->assertSame(2.0, (float) $kpis['scrap']['target_max_pct']);
        $this->assertSame(95.0, (float) $kpis['fpy']['target_min_pct']);
    }

    public function test_scrap_tone_follows_the_saved_target(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $this->scrap($this->produced($line, 100, '09:00'), 3, $this->at('09:00')); // 3 %

        $this->assertSame('warn', $this->kpis()['scrap']['tone']); // target 2 %

        // Changed through the Settings form, as an admin would.
        $this->actingAs($this->admin)
            ->post('/settings/production-targets', ['max_scrap_rate_pct' => 5, 'min_fpy_pct' => 90])
            ->assertRedirect();

        $kpis = $this->kpis();
        $this->assertSame(5.0, (float) $kpis['scrap']['target_max_pct']);
        $this->assertSame('ok', $kpis['scrap']['tone']);
        $this->assertSame(90.0, (float) $kpis['fpy']['target_min_pct']);

        ProductionTargetSettings::save(1.0, 90.0);
        $this->assertSame('bad', $this->kpis()['scrap']['tone']); // 3 % > 2 × 1 %
    }

    // ── first-pass yield ───────────────────────────────────────────────

    private function checked(bool $passed, string $at): void
    {
        $order = $this->order(['line_id' => Line::factory()->create()->id]);
        $batch = Batch::factory()->create(['work_order_id' => $order->id]);
        QualityCheck::factory()->create(['batch_id' => $batch->id, 'checked_at' => $this->at($at), 'all_passed' => $passed]);
    }

    public function test_fpy_with_checks_matches_the_service_and_target(): void
    {
        foreach (['07:00', '08:00', '09:00'] as $time) {
            $this->checked(true, $time);
        }
        $this->checked(false, '09:30');
        $this->checked(true, '05:00'); // before the shift

        $fpy = $this->kpis()['fpy'];

        $this->assertTrue($fpy['available']);
        $this->assertSame(4, $fpy['inspected']);
        $this->assertSame(3, $fpy['first_pass']);
        $this->assertSame(75.0, (float) $fpy['pct']);
        $this->assertSame(app(FirstPassYieldService::class)->forWindow($this->at('06:00'), $this->at('14:00')), (float) $fpy['pct']);
        $this->assertSame('bad', $fpy['tone']); // 75 < 95 − 5

        ProductionTargetSettings::save(2.0, 78.0);
        $this->assertSame('warn', $this->kpis()['fpy']['tone']);
        ProductionTargetSettings::save(2.0, 75.0);
        $this->assertSame('ok', $this->kpis()['fpy']['tone']);
    }

    public function test_fpy_without_checks_this_shift_is_null(): void
    {
        $fpy = $this->kpis()['fpy'];

        $this->assertTrue($fpy['available']);
        $this->assertNull($fpy['pct']);
        $this->assertSame('mute', $fpy['tone']);
        $this->assertSame('/admin/quality-tasks', $fpy['href']);
    }

    public function test_fpy_is_unavailable_with_the_quality_module_off(): void
    {
        $this->checked(true, '08:00');
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['quality'])));

        $fpy = $this->kpis()['fpy'];

        $this->assertFalse($fpy['available']);
        $this->assertNull($fpy['pct']);
        $this->assertNull($fpy['href']);
    }

    // ── links / authorization ──────────────────────────────────────────

    public function test_admin_gets_every_link(): void
    {
        $kpis = $this->kpis();

        $this->assertSame('/admin/schedule', $kpis['backlog']['href']);
        $this->assertSame('/admin/shift-monitor', $kpis['lines']['href']);
        $this->assertSame('/admin/scrap-reports', $kpis['scrap']['href']);
        $this->assertSame('/admin/quality-tasks', $kpis['fpy']['href']);
    }

    public function test_links_are_withheld_for_tabs_the_user_cannot_open(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->givePermissionTo(TabRegistry::permission('dashboard'));
        $tabs = TabRegistry::accessibleFor($operator);
        $this->assertNotContains('schedule', $tabs);
        $this->assertNotContains('advanced_reports', $tabs);

        $kpis = $this->kpis($operator);

        $this->assertNull($kpis['backlog']['href']);
        $this->assertNull($kpis['scrap']['href']);
        $this->assertSame(in_array('shift_monitor', $tabs, true) ? '/admin/shift-monitor' : null, $kpis['lines']['href']);
        // The numbers are still there — only the link is gone.
        $this->assertArrayHasKey('count', $kpis['backlog']);
    }

    public function test_fpy_link_is_withheld_with_the_maintenance_module_off(): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['maintenance'])));

        $kpis = $this->kpis();

        $this->assertNull($kpis['fpy']['href']);
        $this->assertTrue($kpis['fpy']['available']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_dashboard_tab_does_not_get_the_tiles(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->get(route('admin.dashboard'))
            ->assertRedirect(TabRegistry::firstAccessibleUrl($supervisor));
    }
}
