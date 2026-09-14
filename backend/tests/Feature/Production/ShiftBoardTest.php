<?php

namespace Tests\Feature\Production;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use App\Models\Line;
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
 * The plant board: every machine in the building as one tile.
 *
 * It answers "what is stopped and why, right now", and it is read from across a
 * room by people who will not touch it. So what it must get right is reach
 * (every line, not one), the cause of the open stop, and the instant that stop
 * began — the tile counts up from it, and getting that wrong under-reports
 * exactly the long stops somebody is standing there watching.
 */
class ShiftBoardTest extends TestCase
{
    use RefreshDatabase;

    private Line $lineA;

    private Line $lineB;

    private Workstation $running;

    private Workstation $stopped;

    private Workstation $onOtherLine;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        $this->lineA = Line::factory()->create(['name' => 'Line A']);
        $this->lineB = Line::factory()->create(['name' => 'Line B']);

        Shift::create([
            'name' => 'Morning', 'code' => 'S1',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => null, 'is_active' => true,
        ]);

        $this->running = Workstation::factory()->create([
            'line_id' => $this->lineA->id, 'code' => 'AAA-1', 'ideal_rate_per_hour' => 600,
        ]);
        $this->stopped = Workstation::factory()->create([
            'line_id' => $this->lineA->id, 'code' => 'BBB-1', 'ideal_rate_per_hour' => 600,
        ]);
        $this->onOtherLine = Workstation::factory()->create([
            'line_id' => $this->lineB->id, 'code' => 'CCC-1', 'ideal_rate_per_hour' => 600,
        ]);
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

    private function supervisor(): User
    {
        Role::findOrCreate('Supervisor', 'web');
        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        return $user;
    }

    /** @return array<string, mixed> */
    private function board(array $query = []): array
    {
        return $this->actingAs($this->supervisor())
            ->getJson(route('supervisor.shift-board.check', $query))
            ->assertOk()
            ->json('data');
    }

    /** @return array<string, mixed>|null */
    private function tile(array $board, string $code): ?array
    {
        return collect($board['stations'])->firstWhere('code', $code);
    }

    public function test_the_board_covers_every_line_at_once(): void
    {
        // The difference from the line overview: no line is selected, and none
        // is left out. A board that showed one line would be the overview with
        // bigger tiles.
        $board = $this->board();

        $this->assertEqualsCanonicalizing(
            ['AAA-1', 'BBB-1', 'CCC-1'],
            collect($board['stations'])->pluck('code')->all(),
        );
    }

    public function test_a_tile_carries_the_line_it_belongs_to(): void
    {
        // The board is flat, so the line has to travel on the tile — it is what
        // tells somebody where to walk once they have spotted a red one.
        $this->assertSame('Line B', $this->tile($this->board(), 'CCC-1')['lineName']);
    }

    public function test_a_stopped_station_shows_its_cause(): void
    {
        $reason = DowntimeReason::create([
            'code' => 'BRK', 'name' => 'Machine Breakdown',
            'kind' => DowntimeKind::Unplanned, 'is_active' => true,
        ]);

        WorkstationState::create([
            'workstation_id' => $this->stopped->id,
            'state' => WorkstationState::FAULT,
            'started_at' => $this->at('09:30:00'),
        ]);
        ProductionDowntime::create([
            'line_id' => $this->lineA->id,
            'workstation_id' => $this->stopped->id,
            'downtime_reason_id' => $reason->id,
            'started_at' => $this->at('09:30:00'),
        ]);

        $tile = $this->tile($this->board(), 'BBB-1');

        $this->assertSame(WorkstationState::FAULT, $tile['state']);
        $this->assertSame('Machine Breakdown', $tile['reason']['name']);
        $this->assertTrue($tile['reason']['countsAsLoss']);
    }

    public function test_an_unclassified_stop_is_flagged_despite_carrying_a_placeholder_cause(): void
    {
        // A machine-reported stop always has *a* reason attached — the AUTO-*
        // placeholder the state machine opens it with, which exists so the OEE
        // arithmetic has something to count against. It is not an explanation,
        // and `needs_reason` is what says so. The tile has to key off the flag
        // rather than off the presence of a name, or every unexplained stop on
        // the board would read as "machine stopped (auto)" — a sentence that
        // answers nothing while looking like an answer.
        $placeholder = DowntimeReason::create([
            'code' => 'AUTO-STOP', 'name' => 'Machine stopped (auto)',
            'kind' => DowntimeKind::Unplanned, 'is_active' => true,
        ]);

        WorkstationState::create([
            'workstation_id' => $this->stopped->id,
            'state' => WorkstationState::STOPPED,
            'started_at' => $this->at('09:00:00'),
        ]);
        ProductionDowntime::create([
            'line_id' => $this->lineA->id,
            'workstation_id' => $this->stopped->id,
            'downtime_reason_id' => $placeholder->id,
            'needs_reason' => true,
            'started_at' => $this->at('09:00:00'),
        ]);

        $reason = $this->tile($this->board(), 'BBB-1')['reason'];

        $this->assertTrue($reason['needsReason']);
    }

    public function test_the_clock_counts_from_when_the_stop_really_began(): void
    {
        // The heart of it. The shift started at 06:00 and this stop at 04:00,
        // the shift before. The minute arithmetic behind the OEE figures clips
        // to the shift on purpose — but the tile's clock must not, or a stop
        // running into its fifth hour would be announced as two.
        $reason = DowntimeReason::create([
            'code' => 'BRK', 'name' => 'Machine Breakdown',
            'kind' => DowntimeKind::Unplanned, 'is_active' => true,
        ]);

        WorkstationState::create([
            'workstation_id' => $this->stopped->id,
            'state' => WorkstationState::FAULT,
            'started_at' => $this->at('04:00:00'),
        ]);
        ProductionDowntime::create([
            'line_id' => $this->lineA->id,
            'workstation_id' => $this->stopped->id,
            'downtime_reason_id' => $reason->id,
            'started_at' => $this->at('04:00:00'),
        ]);

        $tile = $this->tile($this->board(), 'BBB-1');

        $this->assertSame(
            $this->at('04:00:00')->toIso8601String(),
            $tile['since'],
        );
    }

    public function test_a_station_nobody_has_heard_from_is_not_reported_as_idle(): void
    {
        // No state rows at all. Answering "IDLE" would be an invention, and a
        // dead collector reading as a quiet machine is what stops anyone
        // investigating it.
        $tile = $this->tile($this->board(), 'CCC-1');

        $this->assertNull($tile['state']);
        $this->assertNull($tile['since']);
        $this->assertNull($tile['reason']);
    }

    public function test_the_tile_carries_the_three_oee_components(): void
    {
        // Availability, performance and quality separately — one combined score
        // cannot say which of the three is the problem, which is the only thing
        // worth knowing from across a room.
        WorkstationState::create([
            'workstation_id' => $this->running->id,
            'state' => WorkstationState::RUNNING,
            'started_at' => $this->at('06:00:00'),
        ]);

        $tile = $this->tile($this->board(), 'AAA-1');

        $this->assertArrayHasKey('availability', $tile);
        $this->assertArrayHasKey('performance', $tile);
        $this->assertArrayHasKey('quality', $tile);
        $this->assertEqualsWithDelta(100, $tile['availability'], 0.01);
    }

    public function test_a_kiosk_url_can_narrow_the_board_to_chosen_lines(): void
    {
        $board = $this->board(['lines' => (string) $this->lineB->id]);

        $this->assertSame(['CCC-1'], collect($board['stations'])->pluck('code')->all());
    }

    public function test_a_kiosk_url_naming_a_line_that_is_gone_shows_nothing(): void
    {
        // Not the whole plant. A wall display quietly widening back after a line
        // was renumbered is a failure nobody in the building would notice.
        $board = $this->board(['lines' => '999999']);

        $this->assertSame([], $board['stations']);
    }

    public function test_an_inactive_station_is_off_the_board(): void
    {
        $this->onOtherLine->update(['is_active' => false]);

        $this->assertNull($this->tile($this->board(), 'CCC-1'));
    }

    public function test_a_guest_is_turned_away(): void
    {
        $this->getJson(route('supervisor.shift-board.check'))->assertUnauthorized();
    }

    public function test_an_operator_cannot_open_the_board(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->get(route('supervisor.shift-board.index'))
            ->assertForbidden();
    }
}
