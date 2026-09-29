<?php

namespace Tests\Feature\Seeders;

use App\Models\WorkOrder;
use Database\Seeders\AirFilterDemoSeeder;
use Database\Seeders\BakeryDemoSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\PanelFurnitureDemoSeeder;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The demos anchor their running orders on today's shift start. Seeded (or
 * re-seeded) before that hour, a running order would start in the future, and
 * the planned-start guard refuses any change to its counters - so the suite
 * failed every night until the shift began.
 */
class DemoSeedersBeforeShiftTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array<int, class-string>> */
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
    public function test_a_demo_reseeds_before_the_shift_starts(string $seeder): void
    {
        $this->travelTo(Carbon::parse('2026-03-10 05:00'));

        $this->seed($seeder);
        $this->seed($seeder);

        // Reached without the guard refusing a running order's counters.
        $this->assertGreaterThan(0, WorkOrder::where('status', WorkOrder::STATUS_IN_PROGRESS)->count());
    }
}
