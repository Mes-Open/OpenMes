<?php

namespace Tests\Feature\Seeders;

use App\Models\WorkOrder;
use Database\Seeders\AirFilterDemoSeeder;
use Database\Seeders\BakeryDemoSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How far the demo board reaches.
 *
 * The planner exists to show what is coming, so a dataset that stops a
 * fortnight out leaves the far half of the horizon blank and the board reads as
 * abandoned. Backwards matters too: paging into last week should find work
 * rather than empty columns.
 */
class DemoHorizonTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array<int, class-string>> */
    public static function seeders(): array
    {
        return [
            'print shop' => [PrintShopDemoSeeder::class],
            'machine shop' => [MachineShopDemoSeeder::class],
            'bakery' => [BakeryDemoSeeder::class],
            'air filter' => [AirFilterDemoSeeder::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_the_board_reaches_about_four_weeks_ahead(string $seeder): void
    {
        $this->seed($seeder);

        $furthest = WorkOrder::max('due_date');

        $this->assertNotNull($furthest, 'No orders at all.');
        $this->assertGreaterThanOrEqual(
            25,
            (int) now()->diffInDays($furthest, false),
            'The planner runs dry well inside the horizon it is meant to show.',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_every_week_of_the_horizon_has_work_in_it(string $seeder): void
    {
        $this->seed($seeder);

        // A gap reads as a mistake rather than a quiet week, and hides whether
        // the board can render that far at all.
        for ($week = 0; $week < 4; $week++) {
            $from = now()->copy()->addWeeks($week)->startOfDay();
            $to = now()->copy()->addWeeks($week + 1)->endOfDay();

            $this->assertGreaterThan(
                0,
                WorkOrder::whereBetween('due_date', [$from, $to])->count(),
                "Week +{$week} of the horizon is empty.",
            );
        }
    }

    public function test_the_print_shop_also_covers_the_week_behind(?string $seeder = null): void
    {
        $this->seed(PrintShopDemoSeeder::class);

        // Paging back is how a supervisor checks what just happened.
        $this->assertGreaterThan(
            0,
            WorkOrder::whereBetween('due_date', [now()->copy()->subWeek(), now()])->count(),
            'Last week is empty, so paging back finds nothing.',
        );
    }
}
