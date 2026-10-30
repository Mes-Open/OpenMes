<?php

namespace Tests\Feature\Web;

use App\Enums\IssueDisposition;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filing an issue from the Issues page (POST /admin/issues, /supervisor/issues),
 * where the reporter picks its effect: none, block the work order, or scrap.
 */
class ManagedIssueReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $supervisor;

    protected User $operator;

    protected WorkOrder $order;

    protected IssueType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $this->order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $this->type = IssueType::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'work_order_id' => $this->order->id,
            'issue_type_id' => $this->type->id,
            'title' => 'Seal leaking on station 2',
            'description' => 'Visible drip under the press.',
            'effect' => 'none',
        ], $overrides);
    }

    // ── Happy path ───────────────────────────────────────────────────────────

    public function test_admin_files_an_issue_with_no_effect(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/issues', $this->payload());

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $issue = Issue::sole();
        $this->assertSame('Seal leaking on station 2', $issue->title);
        $this->assertSame(Issue::STATUS_OPEN, $issue->status);
        $this->assertSame($this->admin->id, $issue->reported_by_id);
        $this->assertFalse($issue->blocks_work_order);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->order->fresh()->status);
        $this->assertSame(0, ScrapEntry::count());
    }

    public function test_block_effect_blocks_the_work_order_even_for_a_non_blocking_type(): void
    {
        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['effect' => 'block']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Issue::sole()->blocks_work_order);
        $this->assertSame(WorkOrder::STATUS_BLOCKED, $this->order->fresh()->status);
    }

    public function test_resolving_a_blocking_issue_unblocks_the_work_order(): void
    {
        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['effect' => 'block']));
        $issue = Issue::sole();

        $this->actingAs($this->admin)->post("/admin/issues/{$issue->id}/resolve")
            ->assertSessionHasNoErrors();

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->order->fresh()->status);
    }

    public function test_no_effect_overrides_a_blocking_type(): void
    {
        $blockingType = IssueType::factory()->blocking()->create();

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['issue_type_id' => $blockingType->id]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(Issue::sole()->blocks_work_order);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->order->fresh()->status);
    }

    public function test_scrap_effect_records_a_linked_scrap_entry_and_scrap_disposition(): void
    {
        $reason = ScrapReason::factory()->create();

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload([
            'effect' => 'scrap',
            'scrap_reason_id' => $reason->id,
            'quantity' => '12.5',
        ]))->assertSessionHasNoErrors();

        $issue = Issue::sole();
        $entry = ScrapEntry::sole();

        $this->assertSame($issue->id, $entry->issue_id);
        $this->assertSame($this->order->id, $entry->work_order_id);
        $this->assertSame($reason->id, $entry->scrap_reason_id);
        $this->assertEquals(12.5, (float) $entry->quantity);
        $this->assertSame($this->admin->id, $entry->reported_by);

        $this->assertFalse($issue->blocks_work_order);
        $this->assertSame(IssueDisposition::Scrap->value, $issue->disposition);
        $this->assertEquals(12.5, (float) $issue->non_conforming_qty);
        $this->assertSame($this->admin->id, $issue->disposition_by_id);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->order->fresh()->status);
    }

    public function test_supervisor_can_file_an_issue(): void
    {
        $this->actingAs($this->supervisor)->post('/supervisor/issues', $this->payload(['effect' => 'block']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(WorkOrder::STATUS_BLOCKED, $this->order->fresh()->status);
    }

    public function test_issues_page_offers_the_report_dialog_data(): void
    {
        $this->actingAs($this->admin)->get('/admin/issues')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shared/issues/Index')
                ->has('issueTypes')
                ->has('scrapReasons')
                ->has('reportableWorkOrders', 1)
                ->where('reportableWorkOrders.0.can_block', true)
                ->where('reportableWorkOrders.0.scrap_on_steps', false)
                ->has('issueCustomFields'));
    }

    // ── Effect flag defaults (other entry points) ────────────────────────────

    public function test_an_issue_created_without_an_effect_follows_its_type(): void
    {
        $blocking = Issue::factory()->create(['issue_type_id' => IssueType::factory()->blocking()]);
        $plain = Issue::factory()->create(['issue_type_id' => $this->type->id]);

        $this->assertTrue($blocking->fresh()->blocks_work_order);
        $this->assertFalse($plain->fresh()->blocks_work_order);
    }

    public function test_resolving_restores_the_status_the_block_replaced(): void
    {
        $this->order->update(['status' => WorkOrder::STATUS_PENDING]);

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['effect' => 'block']));
        $order = $this->order->fresh();
        $this->assertSame(WorkOrder::STATUS_BLOCKED, $order->status);
        $this->assertSame(WorkOrder::STATUS_PENDING, $order->status_before_block);

        $issue = Issue::sole();
        $this->actingAs($this->admin)->post("/admin/issues/{$issue->id}/resolve")->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(WorkOrder::STATUS_PENDING, $order->status);
        $this->assertNull($order->status_before_block);
    }

    public function test_a_second_block_keeps_the_original_status_to_restore(): void
    {
        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['effect' => 'block']));
        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['effect' => 'block', 'title' => 'Second']));

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->order->fresh()->status_before_block);
    }

    public function test_the_service_refuses_block_and_scrap_together(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(\App\Services\IssueService::class)->reportWithEffect(
            data: ['work_order_id' => $this->order->id, 'issue_type_id' => $this->type->id, 'title' => 'x'],
            blocksWorkOrder: true,
            scrap: ['scrap_reason_id' => ScrapReason::factory()->create()->id, 'quantity' => 1],
            userId: $this->admin->id,
        );
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_required_fields_are_validated(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/issues', []);

        $response->assertSessionHasErrors(['work_order_id', 'issue_type_id', 'title', 'effect']);
        $this->assertSame(0, Issue::count());
    }

    public function test_an_unknown_effect_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['effect' => 'stop_line']))
            ->assertSessionHasErrors('effect');

        $this->assertSame(0, Issue::count());
    }

    public function test_scrap_effect_requires_a_reason_and_quantity(): void
    {
        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['effect' => 'scrap']))
            ->assertSessionHasErrors(['scrap_reason_id', 'quantity']);

        $this->assertSame(0, Issue::count());
        $this->assertSame(0, ScrapEntry::count());
    }

    public function test_scrap_quantity_must_be_positive(): void
    {
        $reason = ScrapReason::factory()->create();

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload([
            'effect' => 'scrap',
            'scrap_reason_id' => $reason->id,
            'quantity' => 0,
        ]))->assertSessionHasErrors('quantity');
    }

    public function test_scrap_quantity_allows_at_most_two_decimals(): void
    {
        $reason = ScrapReason::factory()->create();

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload([
            'effect' => 'scrap',
            'scrap_reason_id' => $reason->id,
            'quantity' => '0.015',
        ]))->assertSessionHasErrors('quantity');

        $this->assertSame(0, ScrapEntry::count());
    }

    public function test_an_inactive_scrap_reason_is_rejected(): void
    {
        $reason = ScrapReason::factory()->inactive()->create();

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload([
            'effect' => 'scrap',
            'scrap_reason_id' => $reason->id,
            'quantity' => 3,
        ]))->assertSessionHasErrors('scrap_reason_id');
    }

    public function test_scrap_fields_are_ignored_when_the_effect_is_not_scrap(): void
    {
        $this->actingAs($this->admin)->post('/admin/issues', $this->payload([
            'effect' => 'block',
            'scrap_reason_id' => 999999,
            'quantity' => 'abc',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, ScrapEntry::count());
    }

    // ── Domain edge cases ────────────────────────────────────────────────────

    public function test_a_finished_work_order_cannot_get_a_new_issue(): void
    {
        $this->order->update(['status' => WorkOrder::STATUS_DONE]);

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload())
            ->assertSessionHasErrors('work_order_id');

        $this->assertSame(0, Issue::count());
    }

    public function test_a_rejected_work_order_cannot_get_a_new_issue(): void
    {
        $this->order->update(['status' => WorkOrder::STATUS_REJECTED]);

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['effect' => 'block']))
            ->assertSessionHasErrors('work_order_id');

        $this->assertSame(WorkOrder::STATUS_REJECTED, $this->order->fresh()->status);
    }

    public function test_a_paused_work_order_cannot_be_blocked(): void
    {
        $this->order->update(['status' => WorkOrder::STATUS_PAUSED]);

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['effect' => 'block']))
            ->assertSessionHasErrors('effect');

        $this->assertSame(0, Issue::count());
        $this->assertSame(WorkOrder::STATUS_PAUSED, $this->order->fresh()->status);
    }

    public function test_a_paused_work_order_still_accepts_an_issue_without_a_block(): void
    {
        $this->order->update(['status' => WorkOrder::STATUS_PAUSED]);

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(WorkOrder::STATUS_PAUSED, $this->order->fresh()->status);
    }

    public function test_an_inactive_issue_type_is_rejected(): void
    {
        $inactive = IssueType::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload(['issue_type_id' => $inactive->id]))
            ->assertSessionHasErrors('issue_type_id');
    }

    public function test_scrap_effect_is_refused_on_a_step_ledger_work_order(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $reason = ScrapReason::factory()->create();

        $this->actingAs($this->admin)->post('/admin/issues', $this->payload([
            'effect' => 'scrap',
            'scrap_reason_id' => $reason->id,
            'quantity' => 2,
        ]))->assertSessionHasErrors('effect');

        $this->assertSame(0, Issue::count());
        $this->assertSame(0, ScrapEntry::count());
    }

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/admin/issues', $this->payload())->assertRedirect('/login');
        $this->post('/supervisor/issues', $this->payload())->assertRedirect('/login');

        $this->assertSame(0, Issue::count());
    }

    public function test_operator_cannot_file_through_the_issues_page(): void
    {
        $this->actingAs($this->operator)->post('/admin/issues', $this->payload())->assertForbidden();
        $this->actingAs($this->operator)->post('/supervisor/issues', $this->payload())->assertForbidden();

        $this->assertSame(0, Issue::count());
    }
}
