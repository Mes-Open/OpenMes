<?php

namespace Tests\Feature\Seeders;

use App\Models\Issue;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Production\ShiftMonitorService;
use App\Support\ShiftWindow;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Problems the demo shop floor reported.
 *
 * The seeder created issue *types* — the dictionary an operator picks from —
 * but never an actual report, so the Reported Issues page was empty and neither
 * the planner nor the shift monitor had anything to show.
 *
 * Being in the table is not the same as being visible, which is what these
 * pin down: the monitor only draws an issue whose work order is on that
 * station's line and whose reported_at falls inside the shift being viewed,
 * and the planner has no notion of issues at all — a problem reaches the board
 * only as a blocked order.
 */
class DemoReportedIssuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PrintShopDemoSeeder::class);
    }

    public function test_the_demo_has_reported_issues(): void
    {
        $this->assertGreaterThanOrEqual(5, Issue::count(), 'The Reported Issues page would be empty.');
    }

    public function test_they_cover_the_whole_lifecycle(): void
    {
        $statuses = Issue::pluck('status')->unique();

        // A page where everything is OPEN never shows what the triage actions
        // do, which is most of what the screen is for.
        foreach ([Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED, Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED] as $status) {
            $this->assertContains($status, $statuses->all(), "No issue is {$status}.");
        }
    }

    public function test_timestamps_match_the_status_reached(): void
    {
        foreach (Issue::all() as $issue) {
            $this->assertNotNull($issue->reported_at);

            if ($issue->status === Issue::STATUS_OPEN) {
                // Nothing has happened to it yet, so it cannot claim otherwise.
                $this->assertNull($issue->resolved_at);
                $this->assertNull($issue->closed_at);
            }

            if ($issue->status === Issue::STATUS_CLOSED) {
                $this->assertNotNull($issue->closed_at);
                $this->assertTrue($issue->closed_at->greaterThan($issue->reported_at));
            }
        }
    }

    public function test_an_issue_shows_on_the_shift_monitor_timeline(): void
    {
        $station = Workstation::where('code', 'DTG-1')->firstOrFail();
        $window = ShiftWindow::current($station->line_id);

        $snapshot = app(ShiftMonitorService::class)->snapshot($station, $window);
        $titles = collect($snapshot['events'] ?? [])->pluck('note')->filter();

        $expected = Issue::whereHas('workOrder', fn ($q) => $q->where('line_id', $station->line_id))
            ->whereBetween('reported_at', [$window->start, $window->end])
            ->pluck('title');

        $this->assertNotEmpty($expected, 'No issue falls in the live shift, so the monitor has nothing to draw.');

        foreach ($expected as $title) {
            $this->assertContains($title, $titles->all(), "\"{$title}\" never reached the monitor timeline.");
        }
    }

    public function test_a_blocking_issue_stops_its_order_so_the_planner_sees_it(): void
    {
        $blocked = WorkOrder::where('status', WorkOrder::STATUS_BLOCKED)->get();

        $this->assertNotEmpty($blocked, 'Nothing is blocked, so no problem reaches the planner board.');

        foreach ($blocked as $order) {
            // The block has to be explained by a live blocking issue, not left
            // over from a status the seeder set for its own reasons.
            $this->assertTrue(
                Issue::where('work_order_id', $order->id)
                    ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
                    ->whereHas('issueType', fn ($q) => $q->where('is_blocking', true))
                    ->exists(),
                "{$order->order_no} is blocked with no open blocking issue behind it.",
            );
        }
    }

    public function test_reseeding_does_not_duplicate_reports(): void
    {
        $first = Issue::count();

        $this->seed(PrintShopDemoSeeder::class);

        $this->assertSame($first, Issue::count(), 'A repeat run duplicated the reports.');
    }
}
