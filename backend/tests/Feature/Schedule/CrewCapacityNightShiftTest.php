<?php

namespace Tests\Feature\Schedule;

use App\Extension\Contracts\Null\NullWorkforceProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\Line;
use App\Models\Shift;
use App\Models\Worker;
use App\Models\Workstation;
use App\Services\Schedule\CapacityService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The capacity grid's crew axis reads absences the way the finite scheduler
 * does: a night shift's minutes count on the evening it starts, and only that
 * date's absence takes the worker out of it.
 */
class CrewCapacityNightShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-12 06:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_night_shift_counts_on_its_start_date_and_follows_that_dates_absence(): void
    {
        Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '22:00', 'end_time' => '06:00', 'days_of_week' => [1, 2, 3, 4, 5], 'is_active' => true]);
        $line = Line::factory()->create();
        $worker = new Worker(['name' => 'W']);
        $worker->id = 1;
        $worker->setRelation('workstation', new Workstation(['line_id' => $line->id]));

        $crew = new class
        {
            public int $id = 7;

            public string $name = 'Night crew';

            public string $code = 'N';

            public $workers;

            public $breakWindows;

            public $lines;

            public function relationLoaded($r): bool
            {
                return $r === 'lines';
            }
        };
        $crew->workers = collect([$worker]);
        $crew->breakWindows = collect();
        $crew->lines = collect([$line]);

        // Away on Tuesday: Tuesday's night, not Monday's (which ends Tuesday 06:00).
        $this->app->instance(WorkforceProvider::class, new class($crew) extends NullWorkforceProvider
        {
            public function __construct(private object $crew) {}

            public function crewsForCapacity(): Collection
            {
                return collect([$this->crew]);
            }

            public function absentWorkerIds(CarbonInterface $date): array
            {
                return $date->toDateString() === '2026-10-13' ? [1] : [];
            }
        });

        $monday = Carbon::parse('2026-10-12');
        $grid = app(CapacityService::class)->crewCapacity($monday, $monday->copy()->addDays(2), 'day');
        $cells = collect($grid['resources'])->firstWhere('id', 7)['cells'];

        $this->assertSame(8.0, $cells['2026-10-12']['calendar_h']);
        $this->assertSame(0.0, $cells['2026-10-13']['calendar_h']);
        $this->assertSame(8.0, $cells['2026-10-14']['calendar_h']);
    }
}
