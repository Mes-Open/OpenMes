<?php

namespace Tests\Feature\Seeders;

use App\Models\WorkOrder;
use App\Models\WorkOrderPlacement;
use Database\Seeders\AirFilterDemoSeeder;
use Database\Seeders\BakeryDemoSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\PanelFurnitureDemoSeeder;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Orders that run on more than one line.
 *
 * The planner draws these with a badge on the primary block and a connector
 * down to the extra segment. No demo seeder produced any, so the whole display
 * had nothing behind it — the only way to see it was to drag a block onto a
 * second line by hand.
 */
class DemoMultiLineOrdersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    public static function seeders(): array
    {
        return [
            'print shop' => [PrintShopDemoSeeder::class],
            'machine shop' => [MachineShopDemoSeeder::class],
            'bakery' => [BakeryDemoSeeder::class],
            'panel furniture' => [PanelFurnitureDemoSeeder::class],
            'air filter' => [AirFilterDemoSeeder::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_the_demo_has_orders_running_on_two_lines(string $seeder): void
    {
        $this->seed($seeder);

        $this->assertGreaterThan(
            0,
            WorkOrder::has('extraPlacements')->count(),
            'No order spans two lines, so the planner cannot show one.',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_an_extra_segment_is_on_a_different_line_to_the_primary(string $seeder): void
    {
        $this->seed($seeder);

        foreach (WorkOrderPlacement::with('workOrder')->get() as $placement) {
            // A segment on the order's own line is not a second line — it would
            // draw a connector from a block to itself.
            $this->assertNotSame(
                $placement->workOrder->line_id,
                $placement->line_id,
                "{$placement->workOrder->order_no} has an extra segment on its own line.",
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_segments_land_in_a_shift_column_that_exists(string $seeder): void
    {
        $this->seed($seeder);

        foreach (WorkOrderPlacement::all() as $placement) {
            // The board has three shift columns a day. A fourth would be
            // computed off the end of the row and simply not render.
            $this->assertGreaterThanOrEqual(1, $placement->shift_number);
            $this->assertLessThanOrEqual(3, $placement->shift_number);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_reseeding_does_not_duplicate_segments(string $seeder): void
    {
        $this->seed($seeder);
        $first = WorkOrderPlacement::count();

        $this->seed($seeder);

        $this->assertSame($first, WorkOrderPlacement::count(), 'A repeat run duplicated segments.');
    }
}
