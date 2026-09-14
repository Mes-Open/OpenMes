<?php

namespace Tests\Feature\Seeders;

use App\Models\WorkOrder;
use App\Services\Schedule\SchedulePlannerService;
use Database\Seeders\BakeryDemoSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\PanelFurnitureDemoSeeder;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Work that is accepted but not yet scheduled.
 *
 * Every example company placed all of its orders on the board, so the planner
 * opened with an empty Backlog panel — the one state a real plant never sees.
 * A shop always has orders it has taken and cannot date yet: artwork not
 * approved, material not landed, the customer still deciding.
 *
 * The planner's own definition of the backlog (SchedulePlannerService::board)
 * is an active order with no line, or with neither a due date nor a week. Since
 * work_orders.line_id is NOT NULL, the seeders reach it the second way.
 */
class DemoBacklogTest extends TestCase
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
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, WorkOrder> */
    private function backlog()
    {
        return WorkOrder::whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->where(fn ($q) => $q->whereNull('line_id')
                ->orWhere(fn ($q2) => $q2->whereNull('due_date')->whereNull('week_number')))
            ->get();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_the_planner_opens_with_work_waiting_to_be_scheduled(string $seeder): void
    {
        $this->seed($seeder);

        $this->assertGreaterThanOrEqual(
            3,
            $this->backlog()->count(),
            'The planner would open with an empty Backlog panel.',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_the_backlog_is_a_minority_of_the_board(string $seeder): void
    {
        $this->seed($seeder);

        $active = WorkOrder::whereIn('status', WorkOrder::ACTIVE_STATUSES)->count();

        // A plant whose backlog outweighs its plan is a plant in trouble, and
        // the demo is supposed to look like one that is running well.
        $this->assertLessThan(
            $active / 2,
            $this->backlog()->count(),
            'More work is unscheduled than scheduled.',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_every_waiting_order_still_says_what_it_is(string $seeder): void
    {
        $this->seed($seeder);

        foreach ($this->backlog() as $order) {
            // The backlog card shows the product and the quantity; an order
            // with neither is a blank chip nobody can act on.
            $this->assertNotNull($order->product_type_id, "{$order->order_no} has no product.");
            $this->assertGreaterThan(0, (float) $order->planned_qty, "{$order->order_no} has no quantity.");
            $this->assertNotEmpty($order->description, "{$order->order_no} does not say why it is waiting.");
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_the_planner_actually_hands_them_to_the_backlog_panel(string $seeder): void
    {
        $this->seed($seeder);

        // Asserting through the service rather than the query above: this is
        // what the screen renders, and it is what would break if the planner
        // ever changed its mind about what "unscheduled" means.
        $board = app(SchedulePlannerService::class)->board();

        $this->assertGreaterThanOrEqual(3, count($board['backlogOrders']));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_reseeding_leaves_them_in_the_backlog(string $seeder): void
    {
        $this->seed($seeder);
        $before = $this->backlog()->pluck('order_no')->sort()->values();

        // updateOrCreate only clears a column it names, so the seeders null the
        // scheduling fields explicitly. If that ever regresses, a second run
        // quietly schedules the backlog and this catches it.
        $this->seed($seeder);

        $this->assertSame($before->all(), $this->backlog()->pluck('order_no')->sort()->values()->all());
    }
}
