<?php

namespace Tests\Feature\Seeders;

use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\MachineShopLifecycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The machine-shop demo tells one story, record by record.
 *
 * An assistant reading the seeded data found a tool breakage on a roughing
 * pass filed against a grinding step, an oversize ground bearing seat filed
 * against a turning step, and a blocked order described as "shipped complete".
 * The issues were placed on the first order of a line and later on whatever
 * step was running, or the middle step of the routing — never on the operation
 * their own text names. Scrap and downtime notes had the same fault, keyed by
 * the kind of station rather than the operation: "diameter undersize after
 * finishing pass" on centre drilling, a spindle fault on the furnace.
 */
class MachineShopDemoConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday mid-morning: two days of history to replay and orders
        // running right now, so there is something blocked.
        Carbon::setTestNow(Carbon::parse('2026-01-14 11:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_each_issue_is_filed_against_an_order_of_the_part_it_names(): void
    {
        $this->seed(MachineShopDemoSeeder::class);
        $contexts = MachineShopDemoSeeder::issueContexts();

        $issues = Issue::with('workOrder.productType')->get();
        $this->assertCount(count($contexts), $issues);

        foreach ($issues as $issue) {
            $this->assertSame($contexts[$issue->title]['product'], $issue->workOrder->productType->code, "\"{$issue->title}\" is on the wrong part.");
        }
    }

    public function test_only_a_running_order_is_blocked(): void
    {
        $this->seed(MachineShopDemoSeeder::class);

        foreach (WorkOrder::where('status', WorkOrder::STATUS_BLOCKED)->get() as $order) {
            // Blocked from IN_PROGRESS: a finished order cannot be stopped.
            $this->assertNull($order->completed_at, "{$order->order_no} is blocked yet completed.");
            $this->assertTrue(
                Issue::where('work_order_id', $order->id)->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])->exists(),
                "{$order->order_no} is blocked with no live issue behind it.",
            );
        }

        $this->assertStringNotContainsString('shipped', WorkOrder::where('order_no', 'WO-MS-0008')->value('description'));
    }

    /**
     * One replay for all of it: the lifecycle seeder replays days of production
     * through the real services and takes minutes, so it runs once here.
     */
    public function test_the_lifecycle_replay_keeps_every_record_on_its_operation(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::factory()->create(['username' => 'admin']);
        $this->seed(MachineShopDemoSeeder::class);
        $this->seed(MachineShopLifecycleSeeder::class);

        $this->assertEachIssueSitsOnTheStepItDescribes();
        $this->assertABlockedOrderStoppedOnTheStepOfItsBlockingIssue();
        $this->assertScrapAndDowntimeNotesFitTheOperation();
    }

    private function assertEachIssueSitsOnTheStepItDescribes(): void
    {
        $contexts = MachineShopDemoSeeder::issueContexts();

        // Only the reports the order book seeded; the replay files its own (inbound inspection failures).
        $issues = Issue::with('workOrder.productType')->whereIn('title', array_keys($contexts))->get();
        $this->assertCount(count($contexts), $issues);

        foreach ($issues as $issue) {
            $context = $contexts[$issue->title];
            $this->assertSame($context['product'], $issue->workOrder->productType->code, "\"{$issue->title}\" is on the wrong part.");
            $this->assertNotNull($issue->batch_step_id, "\"{$issue->title}\" is not on any step.");

            $step = BatchStep::with('batch')->find($issue->batch_step_id);
            $this->assertSame($context['step'], $step->name, "\"{$issue->title}\" is on the wrong step.");
            $this->assertSame($issue->work_order_id, $step->batch->work_order_id, "\"{$issue->title}\" is on another order's step.");
        }

        $breakage = Issue::where('title', 'like', 'Insert shattered%')->firstOrFail();
        $this->assertMatchesRegularExpression('/turn|rough/i', $breakage->batchStep->name);

        $oversize = Issue::where('title', 'like', 'Bearing seat grinding%')->firstOrFail();
        $this->assertMatchesRegularExpression('/grind/i', $oversize->batchStep->name);
    }

    private function assertABlockedOrderStoppedOnTheStepOfItsBlockingIssue(): void
    {
        $blocked = WorkOrder::where('status', WorkOrder::STATUS_BLOCKED)->get();
        $this->assertNotEmpty($blocked, 'Nothing is blocked, so the test proves nothing.');

        foreach ($blocked as $order) {
            $issue = Issue::where('work_order_id', $order->id)
                ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
                ->where('blocks_work_order', true)->first();
            $this->assertNotNull($issue, "{$order->order_no} is blocked with no open blocking issue.");

            $running = BatchStep::whereHas('batch', fn ($q) => $q->where('work_order_id', $order->id))
                ->where('status', BatchStep::STATUS_IN_PROGRESS)->pluck('name')->all();
            $this->assertContains($issue->batchStep->name, $running, "{$order->order_no} stopped somewhere other than \"{$issue->batchStep->name}\".");
        }
    }

    private function assertScrapAndDowntimeNotesFitTheOperation(): void
    {
        $notes = (new \ReflectionClassConstant(MachineShopLifecycleSeeder::class, 'SCRAP_BY_STEP'))->getValue();
        $scrap = DB::table('scrap_entries as s')->join('batch_steps as b', 'b.id', '=', 's.batch_step_id')->get(['b.name', 's.notes']);
        $this->assertNotEmpty($scrap);
        foreach ($scrap as $entry) {
            $this->assertArrayHasKey($entry->name, $notes, "Scrap recorded on \"{$entry->name}\", which removes no metal.");
            $this->assertSame($notes[$entry->name][0], $entry->notes, "Scrap on \"{$entry->name}\" has someone else's note.");
        }

        $downtimes = DB::table('production_downtimes as d')->join('workstations as w', 'w.id', '=', 'd.workstation_id')->get(['w.code', 'd.notes']);
        $this->assertNotEmpty($downtimes);
        foreach ($downtimes as $downtime) {
            $cuts = (bool) preg_match('/^(LATHE|MILL|BARFEED|GRIND)/', $downtime->code);
            if (! $cuts) {
                $this->assertDoesNotMatchRegularExpression('/spindle|offsets/i', $downtime->notes, "{$downtime->code}: {$downtime->notes}");
            }
            if (! preg_match('/^(LATHE|MILL|BARFEED|SAW)/', $downtime->code)) {
                $this->assertStringNotContainsString('Chip conveyor', $downtime->notes, "{$downtime->code}: {$downtime->notes}");
            }
        }
    }
}
