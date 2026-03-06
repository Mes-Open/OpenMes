<?php

namespace Tests\Unit\Models;

use App\Models\Batch;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderModelTest extends TestCase
{
    use RefreshDatabase;

    protected WorkOrder $workOrder;
    protected IssueType $blockingType;
    protected IssueType $nonBlockingType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workOrder = WorkOrder::factory()->create([
            'planned_qty'  => 100,
            'produced_qty' => 0,
            'status'       => WorkOrder::STATUS_PENDING,
        ]);

        $this->blockingType = IssueType::factory()->create([
            'code'        => 'BLK',
            'is_blocking' => true,
        ]);

        $this->nonBlockingType = IssueType::factory()->create([
            'code'        => 'NBLK',
            'is_blocking' => false,
        ]);
    }

    // ── Status constants ─────────────────────────────────────────────────────

    public function test_status_constants_defined(): void
    {
        $this->assertEquals('PENDING', WorkOrder::STATUS_PENDING);
        $this->assertEquals('ACCEPTED', WorkOrder::STATUS_ACCEPTED);
        $this->assertEquals('IN_PROGRESS', WorkOrder::STATUS_IN_PROGRESS);
        $this->assertEquals('BLOCKED', WorkOrder::STATUS_BLOCKED);
        $this->assertEquals('PAUSED', WorkOrder::STATUS_PAUSED);
        $this->assertEquals('DONE', WorkOrder::STATUS_DONE);
        $this->assertEquals('REJECTED', WorkOrder::STATUS_REJECTED);
        $this->assertEquals('CANCELLED', WorkOrder::STATUS_CANCELLED);
    }

    public function test_active_statuses_contains_expected_values(): void
    {
        $this->assertContains(WorkOrder::STATUS_PENDING, WorkOrder::ACTIVE_STATUSES);
        $this->assertContains(WorkOrder::STATUS_ACCEPTED, WorkOrder::ACTIVE_STATUSES);
        $this->assertContains(WorkOrder::STATUS_IN_PROGRESS, WorkOrder::ACTIVE_STATUSES);
        $this->assertContains(WorkOrder::STATUS_BLOCKED, WorkOrder::ACTIVE_STATUSES);
        $this->assertNotContains(WorkOrder::STATUS_DONE, WorkOrder::ACTIVE_STATUSES);
        $this->assertNotContains(WorkOrder::STATUS_CANCELLED, WorkOrder::ACTIVE_STATUSES);
    }

    public function test_terminal_statuses_contains_expected_values(): void
    {
        $this->assertContains(WorkOrder::STATUS_DONE, WorkOrder::TERMINAL_STATUSES);
        $this->assertContains(WorkOrder::STATUS_REJECTED, WorkOrder::TERMINAL_STATUSES);
        $this->assertContains(WorkOrder::STATUS_CANCELLED, WorkOrder::TERMINAL_STATUSES);
        $this->assertNotContains(WorkOrder::STATUS_PENDING, WorkOrder::TERMINAL_STATUSES);
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function test_belongs_to_line(): void
    {
        $line = Line::factory()->create();
        $wo = WorkOrder::factory()->create(['line_id' => $line->id]);

        $this->assertEquals($line->id, $wo->line->id);
    }

    public function test_belongs_to_product_type(): void
    {
        $productType = ProductType::factory()->create();
        $wo = WorkOrder::factory()->create(['product_type_id' => $productType->id]);

        $this->assertEquals($productType->id, $wo->productType->id);
    }

    public function test_has_many_batches(): void
    {
        Batch::factory()->count(3)->create(['work_order_id' => $this->workOrder->id]);

        $this->assertCount(3, $this->workOrder->batches);
    }

    public function test_has_many_issues(): void
    {
        Issue::factory()->count(2)->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
        ]);

        $this->assertCount(2, $this->workOrder->issues);
    }

    // ── isBlocked() ──────────────────────────────────────────────────────────

    public function test_is_blocked_returns_false_with_no_issues(): void
    {
        $this->assertFalse($this->workOrder->isBlocked());
    }

    public function test_is_blocked_returns_false_with_only_non_blocking_issues(): void
    {
        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->nonBlockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);

        $this->assertFalse($this->workOrder->isBlocked());
    }

    public function test_is_blocked_returns_true_with_open_blocking_issue(): void
    {
        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_OPEN,
        ]);

        $this->assertTrue($this->workOrder->fresh()->isBlocked());
    }

    public function test_is_blocked_returns_true_with_acknowledged_blocking_issue(): void
    {
        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_ACKNOWLEDGED,
        ]);

        $this->assertTrue($this->workOrder->fresh()->isBlocked());
    }

    public function test_is_blocked_returns_false_when_blocking_issue_resolved(): void
    {
        Issue::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'issue_type_id' => $this->blockingType->id,
            'status'        => Issue::STATUS_RESOLVED,
        ]);

        $this->assertFalse($this->workOrder->fresh()->isBlocked());
    }

    // ── isComplete() ─────────────────────────────────────────────────────────

    public function test_is_complete_returns_false_when_under_planned_qty(): void
    {
        $this->workOrder->update(['planned_qty' => 100, 'produced_qty' => 50]);

        $this->assertFalse($this->workOrder->fresh()->isComplete());
    }

    public function test_is_complete_returns_true_when_produced_equals_planned(): void
    {
        $this->workOrder->update(['planned_qty' => 100, 'produced_qty' => 100]);

        $this->assertTrue($this->workOrder->fresh()->isComplete());
    }

    public function test_is_complete_returns_true_when_produced_exceeds_planned(): void
    {
        $this->workOrder->update(['planned_qty' => 100, 'produced_qty' => 110]);

        $this->assertTrue($this->workOrder->fresh()->isComplete());
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function test_scope_status_filters_by_status(): void
    {
        WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);
        WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE]);
        WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE]);

        $done = WorkOrder::status(WorkOrder::STATUS_DONE)->get();

        foreach ($done as $wo) {
            $this->assertEquals(WorkOrder::STATUS_DONE, $wo->status);
        }
        $this->assertGreaterThanOrEqual(2, $done->count());
    }

    public function test_scope_for_line_filters_by_line(): void
    {
        $line1 = Line::factory()->create();
        $line2 = Line::factory()->create();

        WorkOrder::factory()->count(3)->create(['line_id' => $line1->id]);
        WorkOrder::factory()->count(2)->create(['line_id' => $line2->id]);

        $result = WorkOrder::forLine($line1->id)->get();

        $this->assertCount(3, $result);
        foreach ($result as $wo) {
            $this->assertEquals($line1->id, $wo->line_id);
        }
    }

    // ── Casts ────────────────────────────────────────────────────────────────

    public function test_process_snapshot_is_cast_to_array(): void
    {
        $wo = WorkOrder::factory()->create();

        if ($wo->process_snapshot !== null) {
            $this->assertIsArray($wo->process_snapshot);
        } else {
            $this->assertNull($wo->process_snapshot);
        }
    }

    public function test_extra_data_is_cast_to_array(): void
    {
        $wo = WorkOrder::factory()->create(['extra_data' => ['key' => 'value']]);

        $this->assertIsArray($wo->extra_data);
        $this->assertEquals('value', $wo->extra_data['key']);
    }

    public function test_due_date_is_cast_to_datetime(): void
    {
        $wo = WorkOrder::factory()->create(['due_date' => now()->addDays(7)]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $wo->due_date);
    }
}
