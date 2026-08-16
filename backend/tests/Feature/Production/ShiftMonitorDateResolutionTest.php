<?php

namespace Tests\Feature\Production;

use App\Models\Line;
use App\Models\MachineEvent;
use App\Models\Shift;
use App\Models\User;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Production\ShiftMonitorService;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Which shift the monitor shows for a requested date.
 *
 * `?date=` is a business date — the day the shift *opened* — and the screen
 * pages through shifts by stepping it. Both halves of that were wrong: the date
 * was combined with the current clock time (so a night shift resolved to the
 * day before whenever the request arrived after midnight), and a date with no
 * shift scheduled fell through to whatever is running now, which left the
 * arrows apparently dead and showed live data under a past date.
 */
class ShiftMonitorDateResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Workstation $workstation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->line = Line::factory()->create();
        $this->workstation = Workstation::factory()->create([
            'line_id' => $this->line->id,
            'ideal_rate_per_hour' => 600,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function nightShift(string $days = '1,2,3,4,5,6,7'): Shift
    {
        return Shift::create([
            'name' => 'Night', 'code' => 'S3',
            'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'line_id' => $this->line->id, 'is_active' => true,
            'days_of_week' => $days,
        ]);
    }

    private function supervisor(): User
    {
        Role::findOrCreate('Supervisor', 'web');
        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        return $user;
    }

    public function test_a_night_shift_asked_for_by_date_resolves_to_that_night(): void
    {
        $shift = $this->nightShift();
        // 03:00 — inside the night that opened on the 26th, and *before* the
        // 22:00 start time, which is the branch that used to roll the answer
        // back a day.
        Carbon::setTestNow(Carbon::parse('2026-05-27 03:00:00'));

        $response = $this->actingAs($this->supervisor())
            ->getJson(route('supervisor.shift-monitor.check', [
                'workstation' => $this->workstation->id,
                'shift' => $shift->id,
                'date' => '2026-05-27',
            ]))
            ->assertOk();

        $this->assertSame('2026-05-27', $response->json('selected.date'));
    }

    public function test_stepping_back_a_day_moves_exactly_one_shift(): void
    {
        $shift = $this->nightShift();
        Carbon::setTestNow(Carbon::parse('2026-05-27 03:00:00'));

        // What the ‹ button sends after the request above.
        $response = $this->actingAs($this->supervisor())
            ->getJson(route('supervisor.shift-monitor.check', [
                'workstation' => $this->workstation->id,
                'shift' => $shift->id,
                'date' => '2026-05-26',
            ]))
            ->assertOk();

        $this->assertSame('2026-05-26', $response->json('selected.date'), 'one press, one shift');
    }

    public function test_todays_date_with_no_shift_resolves_to_the_one_running_now(): void
    {
        // Two shifts on the line. Stepping to another station keeps the date
        // and drops the shift id (shift ids belong to a line), so the server is
        // asked "the 26th, this station" while the afternoon is running.
        Shift::create([
            'name' => 'Morning', 'code' => 'S1',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => $this->line->id, 'is_active' => true,
        ]);
        $afternoon = Shift::create([
            'name' => 'Afternoon', 'code' => 'S2',
            'start_time' => '14:00:00', 'end_time' => '22:00:00',
            'line_id' => $this->line->id, 'is_active' => true,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-05-26 17:00:00'));

        $response = $this->actingAs($this->supervisor())
            ->getJson(route('supervisor.shift-monitor.check', [
                'workstation' => $this->workstation->id,
                'date' => '2026-05-26',
            ]))
            ->assertOk();

        // The day's *first* shift would be the morning one, and answering with
        // it moves a supervisor watching the live line four hours into the past
        // for no reason they asked for.
        $this->assertSame($afternoon->id, $response->json('selected.shiftId'));
        $this->assertSame('14', $response->json('data.hours.0.label'));
    }

    public function test_a_day_with_no_scheduled_shift_stays_on_that_day(): void
    {
        // Mon–Fri line; the supervisor pages back into the weekend.
        $this->nightShift(days: '1,2,3,4,5');
        Carbon::setTestNow(Carbon::parse('2026-05-25 10:00:00')); // Monday

        $response = $this->actingAs($this->supervisor())
            ->getJson(route('supervisor.shift-monitor.check', [
                'workstation' => $this->workstation->id,
                'date' => '2026-05-24',   // Sunday
            ]))
            ->assertOk();

        // Not today: showing the live shift under Sunday's date reads as "the
        // weekend was busy" and makes the stepper look broken.
        $this->assertSame('2026-05-24', $response->json('selected.date'));
        $this->assertNull($response->json('selected.shiftId'));
    }

    public function test_hour_rows_follow_the_clock_when_a_shift_starts_mid_hour(): void
    {
        $shift = Shift::create([
            'name' => 'Early', 'code' => 'S0',
            'start_time' => '06:30:00', 'end_time' => '14:30:00',
            'line_id' => $this->line->id, 'is_active' => true,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-05-26 09:00:00'));

        $window = ShiftWindow::startingOn($shift, Carbon::parse('2026-05-26'));

        WorkstationState::create([
            'workstation_id' => $this->workstation->id,
            'state' => WorkstationState::RUNNING,
            'started_at' => $window->start->copy(),
            'ended_at' => $window->start->copy()->addMinutes(120),
        ]);

        // At nameplate, so the stretch reads as running rather than splitting
        // into speed loss for want of a counter feed.
        for ($m = 0; $m < 120; $m++) {
            MachineEvent::create([
                'workstation_id' => $this->workstation->id,
                'event_type' => MachineEvent::TYPE_COUNTER,
                'event_timestamp' => $window->start->copy()->addMinutes($m),
                'payload' => ['delta' => 10, 'kind' => 'good'],
            ]);
        }

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $window);

        // Rows are clock hours, so the first covers 06:00–07:00 with the shift
        // opening halfway through it. Labelling shift-relative blocks by clock
        // hour put every row — and the :15/:30/:45 ruler — 30 minutes out.
        $this->assertSame('06', $snapshot['hours'][0]['label']);
        $this->assertSame('07', $snapshot['hours'][1]['label']);
        $this->assertSame(-30, $snapshot['hours'][0]['from'], 'the row opens before the shift does');

        // And a segment is cut where the clock hour falls, not 60 minutes after
        // the shift began.
        $first = collect($snapshot['hours'][0]['segments'])->firstWhere('kind', 'run');
        $this->assertSame(0, $first['from']);
        $this->assertSame(30, $first['minutes'], '06:30–07:00');
    }
}
