<?php

namespace Tests\Feature\Production;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Services\Production\DowntimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A night shift spans two dates, so "which day does this stop belong to?" is not
 * a question `whereDate()` can answer.
 *
 * This matters more since machine-reported stops started carrying a `shift_id`:
 * they are now visible to the per-shift OEE queries for the first time, and a
 * fault at 01:30 must be charged to the shift that was running when it happened,
 * not to the next occurrence of the same shift.
 */
class OvernightDowntimeAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stop_after_midnight_belongs_to_the_shift_that_was_running(): void
    {
        $line = Line::factory()->create();
        $night = Shift::create([
            'name' => 'Night', 'code' => 'N',
            'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'line_id' => $line->id, 'is_active' => true,
        ]);
        $reason = DowntimeReason::firstOrCreate(
            ['code' => 'TEST-BREAK'],
            ['name' => 'Test breakdown', 'kind' => DowntimeKind::Unplanned->value, 'is_active' => true],
        );

        // 30 minutes down at 01:30 on the 27th — inside the shift that opened
        // 22:00 on the 26th.
        ProductionDowntime::create([
            'line_id' => $line->id,
            'downtime_reason_id' => $reason->id,
            'shift_id' => $night->id,
            'started_at' => Carbon::parse('2026-05-27 01:30:00'),
            'ended_at' => Carbon::parse('2026-05-27 02:00:00'),
            'duration_minutes' => 30,
        ]);

        $service = app(DowntimeService::class);

        $this->assertSame(
            30,
            $service->getLossMinutes($line->id, Carbon::parse('2026-05-26'), $night->id),
            'the stop belongs to the night that began on the 26th',
        );

        $this->assertSame(
            0,
            $service->getLossMinutes($line->id, Carbon::parse('2026-05-27'), $night->id),
            'and must not also be charged to the following night',
        );
    }

    public function test_a_day_shift_is_unaffected(): void
    {
        $line = Line::factory()->create();
        $day = Shift::create([
            'name' => 'Day', 'code' => 'D',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => $line->id, 'is_active' => true,
        ]);
        $reason = DowntimeReason::firstOrCreate(
            ['code' => 'TEST-BREAK2'],
            ['name' => 'Test breakdown', 'kind' => DowntimeKind::Unplanned->value, 'is_active' => true],
        );

        ProductionDowntime::create([
            'line_id' => $line->id,
            'downtime_reason_id' => $reason->id,
            'shift_id' => $day->id,
            'started_at' => Carbon::parse('2026-05-26 09:00:00'),
            'ended_at' => Carbon::parse('2026-05-26 09:15:00'),
            'duration_minutes' => 15,
        ]);

        $service = app(DowntimeService::class);

        $this->assertSame(15, $service->getLossMinutes($line->id, Carbon::parse('2026-05-26'), $day->id));
        $this->assertSame(0, $service->getLossMinutes($line->id, Carbon::parse('2026-05-27'), $day->id));
    }
}
