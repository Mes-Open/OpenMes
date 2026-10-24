<?php

namespace Tests\Feature\Web;

use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workstation;
use App\Services\Dashboard\PlantOverviewService;
use App\Support\TabRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The "Downtime" card of the admin dashboard's plant overview: the current
 * shift's stops grouped by reason, delivered as `overview.downtime`.
 */
class DashboardDowntimeSectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // A Tuesday, mid-morning, four hours into a 06:00–14:00 shift.
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

    private function at(string $time, string $date = '2026-05-26'): Carbon
    {
        return Carbon::parse($date.' '.$time);
    }

    /** @return array<string, mixed> */
    private function downtime(?User $user = null): array
    {
        return $this->actingAs($user ?? $this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->inertiaProps('overview.downtime');
    }

    private function stop(Line $line, DowntimeReason $reason, string $from, ?string $to, array $attrs = []): ProductionDowntime
    {
        $start = str_contains($from, ' ') ? Carbon::parse($from) : $this->at($from);
        $end = $to === null ? null : (str_contains($to, ' ') ? Carbon::parse($to) : $this->at($to));

        return ProductionDowntime::create([
            'line_id' => $line->id,
            'downtime_reason_id' => $reason->id,
            'needs_reason' => false,
            'started_at' => $start,
            'ended_at' => $end,
            'duration_minutes' => $end ? (int) abs($end->diffInMinutes($start)) : null,
            ...$attrs,
        ]);
    }

    /** @return array<string, mixed> */
    private function row(array $downtime, string $name): array
    {
        $row = collect($downtime['reasons'])->firstWhere('name', $name);
        $this->assertNotNull($row, "Reason {$name} missing from downtime");

        return $row;
    }

    // ── sums & sorting ─────────────────────────────────────────────────

    public function test_reasons_are_summed_per_reason_and_sorted_longest_first(): void
    {
        $l1 = Line::factory()->create(['code' => 'L1']);
        $l2 = Line::factory()->create(['code' => 'L2']);
        $l3 = Line::factory()->create(['code' => 'L3']);
        $breakdown = DowntimeReason::factory()->unplanned()->create(['name' => 'Breakdown']);
        $changeover = DowntimeReason::factory()->changeover()->create(['name' => 'Format change']);
        $break = DowntimeReason::factory()->planned()->create(['name' => 'Planned break']);

        $this->stop($l1, $breakdown, '08:00', '09:00');
        $this->stop($l2, $breakdown, '09:00', '09:30');
        $this->stop($l1, $changeover, '07:00', '07:15');
        $this->stop($l3, $break, '06:30', '07:00');

        $dt = $this->downtime();

        $this->assertTrue($dt['has_lines']);
        $this->assertSame(['Breakdown', 'Planned break', 'Format change'], array_column($dt['reasons'], 'name'));
        $this->assertSame([90, 30, 15], array_column($dt['reasons'], 'minutes'));
        $this->assertSame(['L1', 'L2'], $dt['reasons'][0]['lines']);
        $this->assertSame(2, $dt['reasons'][0]['count']);
        $this->assertSame(['unplanned', 'planned', 'changeover'], array_column($dt['reasons'], 'kind'));
        $this->assertSame(135, $dt['total_minutes']);
        $this->assertSame(105, $dt['loss_minutes']);
        $this->assertSame(3, $dt['line_count']);
        $this->assertNull($dt['unclassified']);
    }

    public function test_equal_reasons_are_ordered_by_name(): void
    {
        $line = Line::factory()->create();
        $b = DowntimeReason::factory()->create(['name' => 'Bravo']);
        $a = DowntimeReason::factory()->create(['name' => 'Alpha']);
        $this->stop($line, $b, '07:00', '07:20');
        $this->stop($line, $a, '08:00', '08:20');

        $this->assertSame(['Alpha', 'Bravo'], array_column($this->downtime()['reasons'], 'name'));
    }

    // ── window ─────────────────────────────────────────────────────────

    public function test_open_stop_counts_up_to_now(): void
    {
        $line = Line::factory()->create();
        $reason = DowntimeReason::factory()->unplanned()->create(['name' => 'Jam']);
        $this->stop($line, $reason, '09:20', null);

        $row = $this->row($this->downtime(), 'Jam');
        $this->assertSame(40, $row['minutes']);
        $this->assertTrue($row['open']);

        Carbon::setTestNow($this->at('10:05'));
        $this->assertSame(45, $this->row($this->downtime(), 'Jam')['minutes']);
    }

    public function test_stop_started_before_the_shift_is_clipped_to_the_shift(): void
    {
        $line = Line::factory()->create();
        $reason = DowntimeReason::factory()->unplanned()->create(['name' => 'Breakdown']);
        $this->stop($line, $reason, '05:00', '07:00');            // 60 min inside
        $this->stop($line, $reason, '04:00', '05:30');            // before the shift
        $this->stop($line, $reason, '2026-05-25 08:00', '2026-05-25 09:00'); // yesterday

        $dt = $this->downtime();

        $this->assertSame(60, $this->row($dt, 'Breakdown')['minutes']);
        $this->assertSame(1, $this->row($dt, 'Breakdown')['count']);
        $this->assertSame(60, $dt['total_minutes']);
    }

    public function test_open_stop_from_before_the_shift_counts_from_shift_start(): void
    {
        $line = Line::factory()->create();
        $reason = DowntimeReason::factory()->unplanned()->create(['name' => 'Breakdown']);
        $this->stop($line, $reason, '2026-05-25 22:00', null);

        $this->assertSame(240, $this->downtime()['total_minutes']);
    }

    // ── unclassified ───────────────────────────────────────────────────

    public function test_stops_without_a_cause_are_unclassified(): void
    {
        $line = Line::factory()->create(['code' => 'L1']);
        $station = Workstation::factory()->create(['line_id' => $line->id]);
        $auto = DowntimeReason::factory()->create(['name' => 'AUTO-FAULT', 'code' => 'AUTO-FAULT']);
        $breakdown = DowntimeReason::factory()->unplanned()->create(['name' => 'Breakdown']);

        $this->stop($line, $auto, '07:00', '07:20', ['workstation_id' => $station->id, 'needs_reason' => true]);
        $this->stop($line, $auto, '08:00', '08:10', ['workstation_id' => $station->id, 'needs_reason' => true]);
        $this->stop($line, $breakdown, '09:00', '09:30');

        $dt = $this->downtime();

        $this->assertSame(['Breakdown'], array_column($dt['reasons'], 'name'));
        $this->assertSame(30, $dt['unclassified']['minutes']);
        $this->assertSame(2, $dt['unclassified']['count']);
        $this->assertSame(['L1'], $dt['unclassified']['lines']);
        $this->assertSame($station->id, $dt['unclassified']['workstation_id']);
        $this->assertFalse($dt['unclassified']['open']);
        $this->assertSame(60, $dt['total_minutes']);
        $this->assertSame(60, $dt['loss_minutes']);
    }

    public function test_stop_with_a_deleted_reason_keeps_its_reason_name(): void
    {
        $line = Line::factory()->create();
        $reason = DowntimeReason::factory()->unplanned()->create(['name' => 'Retired cause']);
        $this->stop($line, $reason, '07:00', '07:30');
        $reason->delete();

        $dt = $this->downtime();

        $this->assertSame(30, $this->row($dt, 'Retired cause')['minutes']);
        $this->assertNull($dt['unclassified']);
    }

    // ── planned time ───────────────────────────────────────────────────

    public function test_percent_of_planned_time_excludes_planned_stops(): void
    {
        $l1 = Line::factory()->create();
        $l2 = Line::factory()->create();
        $break = DowntimeReason::factory()->planned()->create(['name' => 'Planned break']);
        $breakdown = DowntimeReason::factory()->unplanned()->create(['name' => 'Breakdown']);
        $this->stop($l1, $break, '08:00', '08:30');
        $this->stop($l2, $breakdown, '09:00', '09:45');

        $dt = $this->downtime();

        // 2 lines × 240 elapsed minutes − 30 planned = 450; 45 / 450 = 10 %.
        $this->assertSame(450, $dt['planned_minutes']);
        $this->assertSame(10, $dt['percent_of_planned']);
        $this->assertSame(75, $dt['total_minutes']);
        $this->assertSame(2, $dt['line_count']);
    }

    public function test_without_lines_there_is_no_planned_time(): void
    {
        $dt = $this->downtime();

        $this->assertFalse($dt['has_lines']);
        $this->assertNull($dt['planned_minutes']);
        $this->assertNull($dt['percent_of_planned']);
        $this->assertSame([], $dt['reasons']);
    }

    // ── line mapping ───────────────────────────────────────────────────

    public function test_station_stop_counts_for_the_stations_line(): void
    {
        $l1 = Line::factory()->create(['code' => 'L1']);
        $l2 = Line::factory()->create(['code' => 'L2']);
        $station = Workstation::factory()->create(['line_id' => $l2->id]);
        $reason = DowntimeReason::factory()->unplanned()->create(['name' => 'Breakdown']);

        ProductionDowntime::create([
            'line_id' => $l1->id, 'workstation_id' => $station->id,
            'downtime_reason_id' => $reason->id,
            'started_at' => $this->at('07:00'), 'ended_at' => $this->at('07:10'),
        ]);

        $this->assertSame(['L2'], $this->row($this->downtime(), 'Breakdown')['lines']);
    }

    public function test_stops_on_inactive_lines_are_excluded(): void
    {
        $active = Line::factory()->create(['code' => 'L1']);
        $inactive = Line::factory()->inactive()->create(['code' => 'OFF']);
        $reason = DowntimeReason::factory()->unplanned()->create(['name' => 'Breakdown']);
        $this->stop($active, $reason, '07:00', '07:10');
        $this->stop($inactive, $reason, '07:00', '08:00');

        $dt = $this->downtime();

        $this->assertSame(10, $dt['total_minutes']);
        $this->assertSame(['L1'], $this->row($dt, 'Breakdown')['lines']);
    }

    // ── empty ──────────────────────────────────────────────────────────

    public function test_lines_without_stops_give_an_empty_section(): void
    {
        Line::factory()->create();

        $dt = $this->downtime();

        $this->assertTrue($dt['has_lines']);
        $this->assertSame(0, $dt['total_minutes']);
        $this->assertSame(0, $dt['line_count']);
        $this->assertSame([], $dt['reasons']);
        $this->assertNull($dt['unclassified']);
        $this->assertSame(0, $dt['percent_of_planned']);
    }

    // ── query budget ───────────────────────────────────────────────────

    public function test_query_count_does_not_grow_with_line_count(): void
    {
        $this->actingAs($this->admin);
        $service = app(PlantOverviewService::class);
        $reason = DowntimeReason::factory()->unplanned()->create();

        $build = function (int $lines) use ($reason) {
            foreach (range(1, $lines) as $i) {
                $line = Line::factory()->create();
                $station = Workstation::factory()->create(['line_id' => $line->id]);
                $this->stop($line, $reason, '07:00', '07:10', ['workstation_id' => $station->id]);
                $this->stop($line, $reason, '08:00', null, ['needs_reason' => true]);
            }
        };

        $count = function () use ($service) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $service->downtime();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $build(1);
        $small = $count();

        $build(5);
        $large = $count();

        $this->assertSame($small, $large, 'downtime() must not issue per-line queries');
    }

    // ── delivery & access ──────────────────────────────────────────────

    public function test_partial_reload_carries_downtime(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('overview.downtime')
                ->reloadOnly('overview', fn (AssertableInertia $reload) => $reload
                    ->has('overview.downtime.reasons')
                    ->missing('materialsStats')));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_dashboard_tab_does_not_get_the_dashboard(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor'); // holds tab:orders, not tab:dashboard

        $this->actingAs($supervisor)
            ->get(route('admin.dashboard'))
            ->assertRedirect(TabRegistry::firstAccessibleUrl($supervisor));
    }

    public function test_downtime_is_tenant_isolated(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('Admin');
        $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $adminB->assignRole('Admin');

        $lineA = Line::factory()->create(['code' => 'A-1', 'tenant_id' => $tenantA->id]);
        $lineB = Line::factory()->create(['code' => 'B-1', 'tenant_id' => $tenantB->id]);
        $reason = DowntimeReason::factory()->unplanned()->create(['name' => 'Breakdown']);
        $this->stop($lineA, $reason, '07:00', '07:10', ['tenant_id' => $tenantA->id]);
        $this->stop($lineB, $reason, '07:00', '08:00', ['tenant_id' => $tenantB->id]);

        $seenByA = $this->downtime($adminA);
        $this->assertSame(10, $seenByA['total_minutes']);
        $this->assertSame(['A-1'], $this->row($seenByA, 'Breakdown')['lines']);

        $seenByB = $this->downtime($adminB);
        $this->assertSame(60, $seenByB['total_minutes']);
        $this->assertSame(['B-1'], $this->row($seenByB, 'Breakdown')['lines']);
    }
}
