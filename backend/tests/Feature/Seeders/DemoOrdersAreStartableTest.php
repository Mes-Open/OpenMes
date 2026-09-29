<?php

namespace Tests\Feature\Seeders;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Database\Seeders\AirFilterDemoSeeder;
use Database\Seeders\BakeryDemoSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\PanelFurnitureDemoSeeder;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demo orders that are running have already started.
 *
 * The seeders wrote `now()->setTime(6, 0)` on orders they marked IN_PROGRESS and
 * relied on nobody looking before six in the morning. The row contradicts
 * itself: the order is running, and its planned start has not happened. The
 * model is right to refuse the next save of such a row — which is exactly what a
 * reseed does — so the whole demo kit fell over, and the suite went red on every
 * nightly run that started early, including on main.
 *
 * The customer met it too, which is the part that matters: sample data loaded
 * before the first shift produced orders nobody could touch until the shift they
 * were nominally already working in.
 *
 * Both tests pin the clock to 05:00, before every shift start in the fixtures.
 * That is deliberate — a later hour would make them pass without asking the
 * question.
 */
class DemoOrdersAreStartableTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        // A Thursday, before any shift in any kit begins. The day matters:
        // PrintShopDemoSeeder plans against now()->next('Monday').
        Carbon::setTestNow(Carbon::parse('2026-01-15 05:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_no_running_demo_order_is_planned_into_the_future(string $seeder): void
    {
        $this->seed($seeder);

        $contradictory = WorkOrder::whereIn('status', [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_DONE])
            ->where('planned_start_at', '>', now())
            ->pluck('order_no')
            ->all();

        $this->assertSame(
            [],
            $contradictory,
            'An order that is running cannot be waiting to start: '.implode(', ', $contradictory),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_reseeding_before_the_first_shift_does_not_fail(string $seeder): void
    {
        // The second run is what breaks: the first only creates rows, and
        // assertProductionAvailable() guards saves of rows that already exist.
        $this->seed($seeder);
        $this->seed($seeder);

        $this->assertGreaterThan(0, WorkOrder::count());
    }
}
