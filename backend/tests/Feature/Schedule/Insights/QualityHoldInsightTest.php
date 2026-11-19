<?php

namespace Tests\Feature\Schedule\Insights;

use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The planner warns about an order it shows in a shift still ahead while a
 * quality hold keeps that order from going on, and the warning goes away —
 * or reopens — with the hold.
 */
class QualityHoldInsightTest extends TestCase
{
    use PlansWithStaff;
    use RefreshDatabase;

    private Line $cnc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlant();
        $this->cnc = $this->plantLine('CNC Turning', 'CNC');
        $this->operator('Jan Kowalski', $this->cnc);
    }

    private function control(WorkOrder $order, bool $blocking = true): QualityControlTask
    {
        $trigger = QualityControlTrigger::factory()->create(['name' => 'First-article check', 'is_blocking' => $blocking]);

        return QualityControlTask::factory()->create([
            'quality_control_trigger_id' => $trigger->id,
            'work_order_id' => $order->id,
            'line_id' => $order->line_id,
        ]);
    }

    public function test_a_planned_order_with_an_open_blocking_control_needs_action(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1);
        $this->control($order);

        $state = $this->drawer($this->planner());
        $insight = $this->insight($state, 'quality_hold');

        $this->assertNotNull($insight);
        $this->assertSame('quality:'.$order->id, $insight['key']);
        $this->assertSame('quality', $insight['category']);
        $this->assertSame('bad', $insight['severity']);
        $this->assertStringContainsString($order->order_no, $insight['title']);
        $this->assertStringContainsString('First-article check', $insight['meta']);
        $this->assertSame([['line_id' => $this->cnc->id, 'date' => '2026-11-24', 'shift' => 1]], $insight['cells']);
        $this->assertSame($order->id, $insight['affected'][0]['work_order_id']);
        $this->assertSame('/admin/quality-tasks', $insight['actions'][0]['href']);
        $this->assertTrue($insight['actions'][0]['best']);
        $this->assertContains('acknowledge', array_column($insight['actions'], 'kind'));
    }

    public function test_non_blocking_or_finished_controls_raise_nothing(): void
    {
        $a = $this->planned($this->cnc, '2026-11-24', 1);
        $b = $this->planned($this->cnc, '2026-11-25', 1);
        $this->control($a, blocking: false);
        $this->control($b)->update(['status' => QualityControlTask::STATUS_DONE, 'completed_at' => now()]);

        $this->assertNull($this->insight($this->drawer($this->planner()), 'quality_hold'));
    }

    public function test_a_blocking_issue_offers_the_issues_page(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1);
        Issue::factory()->create([
            'work_order_id' => $order->id,
            'issue_type_id' => IssueType::factory()->create(['is_blocking' => true])->id,
            'title' => 'Burr on the flange',
            'blocks_work_order' => true,
        ]);

        $insight = $this->insight($this->drawer($this->planner()), 'quality_hold');

        $this->assertStringContainsString('Burr on the flange', $insight['meta']);
        $this->assertSame('/admin/issues', $insight['actions'][0]['href']);
    }

    public function test_an_order_outside_the_visible_week_or_in_a_past_shift_is_not_flagged(): void
    {
        $nextWeek = $this->planned($this->cnc, '2026-12-01', 1);
        $this->control($nextWeek);

        $this->assertNull($this->insight($this->drawer($this->planner()), 'quality_hold'));
    }

    public function test_acknowledged_hold_stays_resolved_until_another_hold_appears(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1);
        $this->control($order);
        $admin = $this->planner();

        $this->actingAs($admin)->postJson('/admin/schedule/insights/acknowledge', $this->board() + ['insight_key' => 'quality:'.$order->id])
            ->assertOk();
        $this->assertNull($this->insight($this->drawer($admin), 'quality_hold'));

        $this->control($order);
        $this->assertNotNull($this->insight($this->drawer($admin), 'quality_hold'));
    }

    public function test_guests_and_operators_cannot_see_or_acknowledge_it(): void
    {
        $order = $this->planned($this->cnc, '2026-11-24', 1);
        $this->control($order);
        $body = $this->board() + ['insight_key' => 'quality:'.$order->id];

        $this->getJson('/admin/schedule/insights?'.http_build_query($this->board()))->assertUnauthorized();
        $this->postJson('/admin/schedule/insights/acknowledge', $body)->assertUnauthorized();
        $this->actingAs($this->planner('Operator'))->postJson('/admin/schedule/insights/acknowledge', $body)->assertForbidden();
    }
}
