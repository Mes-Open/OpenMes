<?php

namespace Tests\Feature\Seeders;

use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\MachineShopLifecycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private const RUNNING = [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_BLOCKED];

    private const LIVE = [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED];

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

    // ── order book (MachineShopDemoSeeder alone) ─────────────────────────────

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
                Issue::where('work_order_id', $order->id)->whereIn('status', self::LIVE)->exists(),
                "{$order->order_no} is blocked with no live issue behind it.",
            );
        }

        $this->assertStringNotContainsString('shipped', WorkOrder::where('order_no', 'WO-MS-0008')->value('description'));
    }

    public function test_a_live_issue_is_on_a_running_order_whenever_its_part_has_one(): void
    {
        $this->seed(MachineShopDemoSeeder::class);
        $products = ProductType::pluck('id', 'code');

        foreach (Issue::with('workOrder')->whereIn('status', self::LIVE)->get() as $issue) {
            $part = MachineShopDemoSeeder::issueContexts()[$issue->title]['product'];
            $partRuns = WorkOrder::where('product_type_id', $products[$part])->whereIn('status', self::RUNNING)->exists();

            if ($partRuns) {
                // Sharing a running order beats a live problem on finished history.
                $this->assertContains($issue->workOrder->status, self::RUNNING, "\"{$issue->title}\" is live on a {$issue->workOrder->status} order.");
            }
        }
    }

    public function test_an_issue_prefers_an_order_on_its_own_line(): void
    {
        $this->seed(MachineShopDemoSeeder::class);
        $products = ProductType::pluck('id', 'code');
        $lines = Line::pluck('id', 'code');

        foreach (Issue::with('workOrder')->get() as $issue) {
            $context = MachineShopDemoSeeder::issueContexts()[$issue->title];
            $fitting = in_array($issue->status, self::LIVE, true) ? self::RUNNING : [WorkOrder::STATUS_DONE];
            $onLine = WorkOrder::where('product_type_id', $products[$context['product']])
                ->where('line_id', $lines[$context['line']])->whereIn('status', $fitting)->whereNotNull('planned_start_at')->exists();

            if ($onLine) {
                $this->assertSame($lines[$context['line']], $issue->workOrder->line_id, "\"{$issue->title}\" left the {$context['line']} monitor.");
            }
        }
    }

    // ── lifecycle replay ─────────────────────────────────────────────────────

    /**
     * The lifecycle replay runs the week through the real services and takes a
     * while, so each clock replays once and every invariant is checked on it.
     * Set MACHINE_SHOP_DEMO_CLOCK to replay at another moment instead.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function clocks(): array
    {
        if ($clock = getenv('MACHINE_SHOP_DEMO_CLOCK')) {
            return [$clock => [$clock, false]];
        }

        return [
            // Two days of history: every seeded issue has a run to belong to.
            'wednesday mid-morning' => ['2026-01-14 11:00:00', true],
            // An hour and a half into the week: little has finished, every live
            // part's lane has only just started.
            'early monday' => ['2026-01-12 07:30:00', false],
        ];
    }

    #[DataProvider('clocks')]
    public function test_the_lifecycle_replay_keeps_every_record_consistent(string $clock, bool $fullHistory): void
    {
        $now = Carbon::parse($clock);
        Carbon::setTestNow($now);

        $this->seed(DatabaseSeeder::class);
        User::factory()->create(['username' => 'admin']);
        $this->seed(MachineShopDemoSeeder::class);
        $this->seed(MachineShopLifecycleSeeder::class);

        $this->assertLiveProblemsStayLive();
        $this->assertEachIssueFitsItsOrderAndStep($now, $fullHistory);
        $this->assertABlockedOrderStoppedPartWay();
        $this->assertScrapAndDowntimeNotesFitTheOperation($fullHistory);
        $this->assertOrderTimelinesHold();
    }

    private function assertLiveProblemsStayLive(): void
    {
        $blocked = WorkOrder::where('status', WorkOrder::STATUS_BLOCKED)->count();
        $this->assertGreaterThanOrEqual(2, $blocked, 'The demo should have both live blocking problems stopping an order.');

        $this->assertSame(Issue::STATUS_ACKNOWLEDGED, Issue::where('title', 'like', 'Bearing seat grinding%')->value('status'));
        $this->assertSame(Issue::STATUS_OPEN, Issue::where('title', 'like', 'Insert shattered%')->value('status'));
    }

    private function assertEachIssueFitsItsOrderAndStep(Carbon $now, bool $fullHistory): void
    {
        $contexts = MachineShopDemoSeeder::issueContexts();

        // Only the reports the order book seeded; the replay files its own (inbound inspection failures).
        $issues = Issue::with('workOrder.productType')->whereIn('title', array_keys($contexts))->get();
        if ($fullHistory) {
            $this->assertCount(count($contexts), $issues);
        }

        foreach ($issues as $issue) {
            $context = $contexts[$issue->title];
            $order = $issue->workOrder;
            $this->assertSame($context['product'], $order->productType->code, "\"{$issue->title}\" is on the wrong part.");

            // The order's state agrees with the issue's: nothing left on an order the re-plan rewrote.
            if (in_array($issue->status, self::LIVE, true)) {
                $this->assertContains($order->status, $issue->blocks_work_order ? [WorkOrder::STATUS_BLOCKED] : self::RUNNING, "\"{$issue->title}\" is live on a {$order->status} order.");
            } else {
                $this->assertSame(WorkOrder::STATUS_DONE, $order->status, "\"{$issue->title}\" is history on a {$order->status} order.");
            }

            $this->assertTrue($issue->reported_at->lte($now), "\"{$issue->title}\" is reported in the future.");
            if ($fullHistory) {
                $this->assertNotNull($issue->batch_step_id, "\"{$issue->title}\" is not on any step.");
            }
            if ($issue->batch_step_id) {
                $step = BatchStep::with('batch')->find($issue->batch_step_id);
                $this->assertSame($context['step'], $step->name, "\"{$issue->title}\" is on the wrong step.");
                $this->assertSame($issue->work_order_id, $step->batch->work_order_id, "\"{$issue->title}\" is on another order's step.");
                if ($step->started_at) {
                    $this->assertTrue($issue->reported_at->gte($step->started_at), "\"{$issue->title}\" is reported before its step started.");
                }
            }
        }

        $breakage = Issue::where('title', 'like', 'Insert shattered%')->firstOrFail();
        $this->assertMatchesRegularExpression('/turn|rough/i', $breakage->batchStep->name);

        $oversize = Issue::where('title', 'like', 'Bearing seat grinding%')->firstOrFail();
        $this->assertMatchesRegularExpression('/grind/i', $oversize->batchStep->name);
    }

    private function assertABlockedOrderStoppedPartWay(): void
    {
        foreach (WorkOrder::where('status', WorkOrder::STATUS_BLOCKED)->get() as $order) {
            $issue = Issue::where('work_order_id', $order->id)->whereIn('status', self::LIVE)->where('blocks_work_order', true)->first();
            $this->assertNotNull($issue, "{$order->order_no} is blocked with no open blocking issue.");
            $this->assertNotNull($issue->batch_step_id, "{$order->order_no}'s blocking issue is on no step.");

            // Stopped part-way through. Usually on the issue's own step; when no batch
            // reached it again before now, the issue sits on the last time it ran.
            $this->assertTrue($order->batches()->exists(), "{$order->order_no} is blocked but never ran.");
            $this->assertNotNull($issue->batchStep->started_at, "{$order->order_no}'s blocking issue is on a step that never ran.");
        }
    }

    private function assertScrapAndDowntimeNotesFitTheOperation(bool $fullHistory): void
    {
        $notes = (new \ReflectionClassConstant(MachineShopLifecycleSeeder::class, 'SCRAP_BY_STEP'))->getValue();
        $scrap = DB::table('scrap_entries as s')->join('batch_steps as b', 'b.id', '=', 's.batch_step_id')->get(['b.name', 's.notes']);
        foreach ($scrap as $entry) {
            $this->assertArrayHasKey($entry->name, $notes, "Scrap recorded on \"{$entry->name}\", which removes no metal.");
            $this->assertSame($notes[$entry->name][0], $entry->notes, "Scrap on \"{$entry->name}\" has someone else's note.");
        }

        $downtimes = DB::table('production_downtimes as d')->join('workstations as w', 'w.id', '=', 'd.workstation_id')->get(['w.code', 'd.notes']);
        if ($fullHistory) {
            $this->assertNotEmpty($scrap);
            $this->assertNotEmpty($downtimes);
        }
        foreach ($downtimes as $downtime) {
            // A bar feeder has neither a spindle nor a chip conveyor of its own.
            if (! preg_match('/^(LATHE|MILL|GRIND)/', $downtime->code)) {
                $this->assertDoesNotMatchRegularExpression('/spindle|offsets/i', $downtime->notes, "{$downtime->code}: {$downtime->notes}");
            }
            if (! preg_match('/^(LATHE|MILL|SAW)/', $downtime->code)) {
                $this->assertStringNotContainsString('Chip conveyor', $downtime->notes, "{$downtime->code}: {$downtime->notes}");
            }
        }
    }

    private function assertOrderTimelinesHold(): void
    {
        // The undated backlog stays undated: its notes say it cannot be dated yet.
        foreach (WorkOrder::whereIn('order_no', ['WO-MS-0050', 'WO-MS-0051', 'WO-MS-0052', 'WO-MS-0053'])->get() as $order) {
            $this->assertNull($order->planned_start_at, "{$order->order_no} was dated.");
            $this->assertSame(WorkOrder::STATUS_PENDING, $order->status);
        }

        // A release baseline is never older than the order it belongs to.
        $early = DB::table('work_order_snapshots as s')->join('work_orders as w', 'w.id', '=', 's.work_order_id')
            ->whereColumn('s.created_at', '<', 'w.created_at')->pluck('w.order_no')->all();
        $this->assertSame([], $early, 'Baseline older than its order: '.implode(', ', $early));
    }
}
