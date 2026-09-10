<?php

namespace Tests\Feature\Seeders;

use App\Models\Batch;
use App\Models\Shift;
use App\Models\Workstation;
use Database\Seeders\AirFilterDemoSeeder;
use Database\Seeders\ShiftMonitorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo dataset behind the shift monitor.
 *
 * Regression guard: the seeder used to hand every station the whole plant's
 * shift roster rather than its own line's, so a fortnight of history became
 * hundreds of windows per station. The batch number packed that window index
 * into a 1000-wide bucket, which then overflowed into the next station's —
 * station 3 window 121 and station 4 window 21 both produced 13210 — and the
 * run died on batches_work_order_id_batch_number_unique with the monitor only
 * half populated.
 */
class ShiftMonitorDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlant(): void
    {
        $this->seed(AirFilterDemoSeeder::class);
    }

    public function test_it_populates_the_monitor(): void
    {
        $this->seedPlant();
        $this->seed(ShiftMonitorDemoSeeder::class);

        // An empty monitor is the failure this whole dataset exists to prevent.
        $this->assertGreaterThan(0, Batch::count(), 'The monitor needs batches to show.');
        $this->assertGreaterThan(0, \DB::table('workstation_states')->count());
    }

    public function test_a_station_only_works_its_own_lines_shifts(): void
    {
        $this->seedPlant();

        // A second line with its own roster. Nothing on the first line should
        // ever be scheduled against these.
        $foreignLine = \App\Models\Line::factory()->create();
        foreach (['X1' => '06:00:00', 'X2' => '14:00:00', 'X3' => '22:00:00'] as $code => $start) {
            Shift::create([
                'line_id' => $foreignLine->id,
                'name' => "Foreign {$code}",
                'code' => $code,
                'start_time' => $start,
                'end_time' => '23:59:00',
                'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                'is_active' => true,
            ]);
        }

        $this->seed(ShiftMonitorDemoSeeder::class);

        $station = Workstation::where('code', 'WS-FR-01')->first();
        $this->assertNotNull($station, 'The air filter demo should have created this station.');

        $foreignCodes = ['X1', 'X2', 'X3'];
        $lots = Batch::where('workstation_id', $station->id)->pluck('lot_number');

        foreach ($lots as $lot) {
            foreach ($foreignCodes as $code) {
                $this->assertStringNotContainsString(
                    "-{$code}",
                    (string) $lot,
                    "Station {$station->code} was scheduled against another line's shift.",
                );
            }
        }
    }

    public function test_batch_numbers_do_not_collide_across_stations(): void
    {
        $this->seedPlant();

        // Enough shifts on the stations' own line to push the fortnight past a
        // hundred windows — the point where the old 1000-wide station bucket
        // started borrowing digits from its neighbour. Three shifts a day never
        // reaches it, so the overflow has to be provoked to be guarded.
        $line = Workstation::where('code', 'WS-FR-01')->firstOrFail()->line_id;
        for ($h = 0; $h < 9; $h++) {
            Shift::create([
                'line_id' => $line,
                'name' => "Dense {$h}",
                'code' => "D{$h}",
                'start_time' => sprintf('%02d:00:00', $h * 2),
                'end_time' => sprintf('%02d:00:00', $h * 2 + 1),
                'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                'is_active' => true,
            ]);
        }

        $this->seed(ShiftMonitorDemoSeeder::class);

        // The unique key the overflow used to violate. Counting distinct pairs
        // against the row count catches a collision that a plain insert would
        // only surface as a database error on some other machine's data volume.
        $pairs = Batch::query()
            ->get(['work_order_id', 'batch_number'])
            ->map(fn ($b) => $b->work_order_id.':'.$b->batch_number);

        $this->assertSame(
            $pairs->count(),
            $pairs->unique()->count(),
            'Two batches share a work order and number.',
        );
    }

    public function test_running_it_twice_is_safe(): void
    {
        $this->seedPlant();
        $this->seed(ShiftMonitorDemoSeeder::class);
        $first = Batch::count();

        // Re-seeding is how the demo gets a fresh live shift, so it has to be
        // repeatable rather than a one-shot that wedges on its own rows.
        $this->seed(ShiftMonitorDemoSeeder::class);

        $this->assertSame($first, Batch::count(), 'A repeat run duplicated batches.');
    }

    public function test_every_example_company_fills_the_monitor(): void
    {
        // The bug users actually hit: loading sample data ran a seeder that
        // creates no workstation states, so the monitor stayed blank even
        // though the dataset for it existed. Whichever company an admin picks,
        // the bundle has to include the seeder that populates the monitor.
        foreach (\App\Support\DemoDatasetRegistry::keys() as $key) {
            $this->assertContains(
                ShiftMonitorDemoSeeder::class,
                \App\Support\DemoDatasetRegistry::seedersFor($key),
                "The {$key} dataset would leave the shift monitor empty.",
            );
        }
    }
}
