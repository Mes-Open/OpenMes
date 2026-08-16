<?php

namespace Tests\Feature\Production;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\MachineEvent;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\User;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The line overview: one row per machine on a line for the running shift.
 *
 * It answers "which machine needs attention", so what it must get right is the
 * comparison — every station present, each with its own timeline and numbers —
 * and who is allowed to see it.
 */
class ShiftOverviewTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Workstation $running;

    private Workstation $stopped;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        $this->line = Line::factory()->create();
        Shift::create([
            'name' => 'Morning', 'code' => 'S1',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => $this->line->id, 'is_active' => true,
        ]);

        $this->running = Workstation::factory()->create([
            'line_id' => $this->line->id, 'code' => 'AAA-1', 'ideal_rate_per_hour' => 600,
        ]);
        $this->stopped = Workstation::factory()->create([
            'line_id' => $this->line->id, 'code' => 'BBB-1', 'ideal_rate_per_hour' => 600,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(int $minute): Carbon
    {
        return Carbon::parse('2026-05-26 06:00:00')->addMinutes($minute);
    }

    private function supervisor(): User
    {
        Role::findOrCreate('Supervisor', 'web');
        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        return $user;
    }

    private function overview(): array
    {
        return $this->actingAs($this->supervisor())
            ->getJson(route('supervisor.shift-overview.check', ['line' => $this->line->id]))
            ->assertOk()
            ->json('data');
    }

    public function test_every_station_on_the_line_gets_a_row(): void
    {
        WorkstationState::create([
            'workstation_id' => $this->running->id,
            'state' => WorkstationState::RUNNING,
            'started_at' => $this->at(0),
            'ended_at' => $this->at(60),
        ]);
        for ($m = 0; $m < 60; $m++) {
            MachineEvent::create([
                'workstation_id' => $this->running->id,
                'event_type' => MachineEvent::TYPE_COUNTER,
                'event_timestamp' => $this->at($m),
                'payload' => ['delta' => 10, 'kind' => 'good'],
            ]);
        }

        $data = $this->overview();
        $rows = collect($data['stations'])->keyBy('code');

        $this->assertCount(2, $rows);
        $this->assertSame(600, $rows['AAA-1']['produced']);
        $this->assertSame(600, $rows['AAA-1']['target']);
        $this->assertNotNull($rows['AAA-1']['oee']);

        // A station nobody is hearing from is exactly what this screen exists
        // to surface, so it appears with no state rather than being left out.
        $this->assertSame(0, $rows['BBB-1']['produced']);
        $this->assertNull($rows['BBB-1']['state']);
    }

    public function test_a_row_reports_its_own_unclassified_stops(): void
    {
        WorkstationState::create([
            'workstation_id' => $this->stopped->id,
            'state' => WorkstationState::STOPPED,
            'started_at' => $this->at(10),
            'ended_at' => $this->at(25),
        ]);
        ProductionDowntime::create([
            'line_id' => $this->line->id,
            'workstation_id' => $this->stopped->id,
            'downtime_reason_id' => DowntimeReason::firstOrCreate(
                ['code' => 'AUTO-STOP'],
                ['name' => 'Auto stop', 'kind' => DowntimeKind::Unplanned->value, 'is_active' => true],
            )->id,
            'needs_reason' => true,
            'started_at' => $this->at(10),
            'ended_at' => $this->at(25),
            'duration_minutes' => 15,
        ]);

        $rows = collect($this->overview()['stations'])->keyBy('code');

        $this->assertSame(1, $rows['BBB-1']['unclassified']);
        $this->assertSame(0, $rows['AAA-1']['unclassified']);
        $this->assertSame(WorkstationState::STOPPED, $rows['BBB-1']['state']);
    }

    public function test_segments_are_positioned_against_the_whole_shift(): void
    {
        WorkstationState::create([
            'workstation_id' => $this->running->id,
            'state' => WorkstationState::RUNNING,
            'started_at' => $this->at(0),
            'ended_at' => $this->at(30),
        ]);

        $rows = collect($this->overview()['stations'])->keyBy('code');

        // The row is the shift end to end — that is what makes two stations
        // comparable — so the client positions segments against this span, not
        // against an hour.
        $this->assertSame(480, $rows['AAA-1']['span']);
        $this->assertSame(240, $rows['AAA-1']['elapsed'], '06:00 shift, 10:00 now');
    }

    public function test_the_overview_is_closed_to_guests_and_operators(): void
    {
        $this->get(route('supervisor.shift-overview.index'))->assertRedirect(route('login'));

        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get(route('supervisor.shift-overview.index'))->assertForbidden();
    }

    public function test_the_admin_mount_serves_the_same_screen(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get(route('admin.shift-overview.index'))
            ->assertOk();
    }
}
