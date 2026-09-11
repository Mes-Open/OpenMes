<?php

namespace Tests\Feature\Seeders;

use App\Models\MaintenanceSchedule;
use App\Models\Tool;
use Database\Seeders\AirFilterDemoSeeder;
use Database\Seeders\BakeryDemoSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\PanelFurnitureDemoSeeder;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The kit maintenance is performed on.
 *
 * The print shop seeded three maintenance schedules and no tools at all, so
 * the Tools page was empty and every schedule pointed at nothing — a weekly
 * printhead clean with no printhead behind it.
 */
class DemoToolsTest extends TestCase
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
    public function test_the_demo_has_tools(string $seeder): void
    {
        $this->seed($seeder);

        $this->assertGreaterThanOrEqual(3, Tool::count(), 'The Tools page would be empty.');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_every_maintenance_schedule_names_the_kit_it_services(string $seeder): void
    {
        $this->seed($seeder);

        $orphans = MaintenanceSchedule::whereNull('tool_id')->pluck('name');

        $this->assertSame(
            [],
            $orphans->all(),
            'Schedules with no tool behind them: '.$orphans->implode(', '),
        );
    }

    public function test_the_print_shop_kit_covers_every_status_and_both_sides_of_today(): void
    {
        $this->seed(PrintShopDemoSeeder::class);

        $statuses = Tool::pluck('status')->unique();

        // All four states the model knows, so the page shows what each looks
        // like rather than sixteen identical rows.
        foreach ([Tool::STATUS_AVAILABLE, Tool::STATUS_IN_USE, Tool::STATUS_MAINTENANCE, Tool::STATUS_RETIRED] as $status) {
            $this->assertContains($status, $statuses->all(), "No tool is {$status}.");
        }

        // Something overdue and something not, so "due soon" has meaning.
        $this->assertGreaterThan(0, Tool::whereDate('next_service_at', '<', now())->count(), 'Nothing is overdue.');
        $this->assertGreaterThan(0, Tool::whereDate('next_service_at', '>', now())->count(), 'Nothing is upcoming.');
    }

    public function test_reseeding_does_not_duplicate_tools(): void
    {
        $this->seed(PrintShopDemoSeeder::class);
        $first = Tool::count();

        $this->seed(PrintShopDemoSeeder::class);

        $this->assertSame($first, Tool::count(), 'A repeat run duplicated the kit.');
    }
}
