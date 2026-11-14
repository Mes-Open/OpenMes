<?php

namespace Tests\Unit\Support;

use App\Support\MachineTime;
use Tests\TestCase;

/**
 * Machine timestamps end up in the timezone set in OpenMES, whatever zone the
 * machine reported them in.
 */
class MachineTimeTest extends TestCase
{
    private string $previousZone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousZone = date_default_timezone_get();
        config(['app.timezone' => 'Europe/Warsaw']);
        date_default_timezone_set('Europe/Warsaw');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousZone);
        parent::tearDown();
    }

    public function test_a_utc_timestamp_is_converted_to_plant_time(): void
    {
        // 10 November: Warsaw is UTC+1.
        $at = MachineTime::parse('2026-11-10T10:00:00Z');

        $this->assertSame('Europe/Warsaw', $at->getTimezone()->getName());
        $this->assertSame('2026-11-10 11:00:00', $at->format('Y-m-d H:i:s'));
    }

    public function test_an_explicit_offset_is_honoured(): void
    {
        $this->assertSame('2026-11-10 09:00:00', MachineTime::parse('2026-11-10T10:00:00+02:00')->format('Y-m-d H:i:s'));
    }

    public function test_a_timestamp_without_offset_is_already_plant_time(): void
    {
        $this->assertSame('2026-11-10 10:00:00', MachineTime::parse('2026-11-10 10:00:00')->format('Y-m-d H:i:s'));
    }

    public function test_epoch_seconds_and_milliseconds_are_recognised(): void
    {
        // 2026-11-10 10:00:00 UTC.
        $this->assertSame('2026-11-10 11:00:00', MachineTime::parse(1794304800)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 11:00:00', MachineTime::parse('1794304800')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 11:00:00.250', MachineTime::parse(1794304800250)->format('Y-m-d H:i:s.v'));
    }

    public function test_empty_values_give_no_timestamp(): void
    {
        $this->assertNull(MachineTime::parse(null));
        $this->assertNull(MachineTime::parse(''));
    }

    public function test_to_plant_keeps_the_instant(): void
    {
        $utc = \Illuminate\Support\Carbon::parse('2026-11-10T10:00:00Z');

        $this->assertTrue(MachineTime::toPlant($utc)->equalTo($utc));
        $this->assertSame('11:00', MachineTime::toPlant($utc)->format('H:i'));
    }
}
