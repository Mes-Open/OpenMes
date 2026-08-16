<?php

namespace Tests\Feature\Web;

use App\Models\Issue;
use App\Models\IssueType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bulk issue transitions from the alerts page's "Acknowledge all"
 * (POST /admin/issues/bulk).
 */
class AdminIssueBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    // ── Happy path ───────────────────────────────────────────────────────────

    public function test_admin_can_acknowledge_many_issues_at_once(): void
    {
        $issues = Issue::factory()->count(3)->create(['status' => Issue::STATUS_OPEN]);

        $response = $this->actingAs($this->admin)
            ->from('/admin/alerts')
            ->post('/admin/issues/bulk', [
                'action' => 'acknowledge',
                'ids' => $issues->pluck('id')->all(),
            ]);

        $response->assertRedirect('/admin/alerts');
        $response->assertSessionHas('success');

        foreach ($issues as $issue) {
            $fresh = $issue->fresh();
            $this->assertSame(Issue::STATUS_ACKNOWLEDGED, $fresh->status);
            $this->assertNotNull($fresh->acknowledged_at);
        }
    }

    public function test_bulk_resolve_records_the_shared_notes(): void
    {
        $issues = Issue::factory()->count(2)->create(['status' => Issue::STATUS_ACKNOWLEDGED]);

        $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'resolve',
            'ids' => $issues->pluck('id')->all(),
            'resolution_notes' => 'Cleared during shift handover.',
        ]);

        foreach ($issues as $issue) {
            $fresh = $issue->fresh();
            $this->assertSame(Issue::STATUS_RESOLVED, $fresh->status);
            $this->assertSame('Cleared during shift handover.', $fresh->resolution_notes);
            $this->assertNotNull($fresh->resolved_at);
        }
    }

    // ── Domain edge cases ────────────────────────────────────────────────────

    public function test_issues_the_action_does_not_apply_to_are_skipped_not_failed(): void
    {
        $open = Issue::factory()->create(['status' => Issue::STATUS_OPEN]);
        $closed = Issue::factory()->create(['status' => Issue::STATUS_CLOSED]);

        $response = $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'acknowledge',
            'ids' => [$open->id, $closed->id],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(Issue::STATUS_ACKNOWLEDGED, $open->fresh()->status);
        $this->assertSame(Issue::STATUS_CLOSED, $closed->fresh()->status);
        // Asserted through the same key the controller uses, so the test doesn't
        // depend on the app's configured locale.
        $this->assertStringContainsString(
            __(':count skipped (not applicable in their current status).', ['count' => 1]),
            session('success'),
        );
    }

    public function test_an_already_acknowledged_issue_is_left_alone_by_acknowledge(): void
    {
        $issue = Issue::factory()->create([
            'status' => Issue::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now()->subDay(),
        ]);
        $acknowledgedAt = $issue->acknowledged_at;

        $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'acknowledge',
            'ids' => [$issue->id],
        ]);

        $fresh = $issue->fresh();
        $this->assertSame(Issue::STATUS_ACKNOWLEDGED, $fresh->status);
        // The original timestamp is what says when it was actually seen.
        $this->assertTrue($acknowledgedAt->equalTo($fresh->acknowledged_at));
    }

    public function test_bulk_resolve_unblocks_a_work_order_with_no_open_blocking_issues_left(): void
    {
        $blockingType = IssueType::factory()->create(['is_blocking' => true]);
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_BLOCKED]);
        $issues = Issue::factory()->count(2)->create([
            'work_order_id' => $order->id,
            'issue_type_id' => $blockingType->id,
            'status' => Issue::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'resolve',
            'ids' => $issues->pluck('id')->all(),
        ]);

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
    }

    public function test_a_work_order_still_blocked_by_another_issue_stays_blocked(): void
    {
        $blockingType = IssueType::factory()->create(['is_blocking' => true]);
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_BLOCKED]);
        $resolved = Issue::factory()->create([
            'work_order_id' => $order->id,
            'issue_type_id' => $blockingType->id,
            'status' => Issue::STATUS_OPEN,
        ]);
        Issue::factory()->create([
            'work_order_id' => $order->id,
            'issue_type_id' => $blockingType->id,
            'status' => Issue::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'resolve',
            'ids' => [$resolved->id],
        ]);

        $this->assertSame(WorkOrder::STATUS_BLOCKED, $order->fresh()->status);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_an_unknown_action_is_rejected(): void
    {
        $issue = Issue::factory()->create(['status' => Issue::STATUS_OPEN]);

        $response = $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'close_everything',
            'ids' => [$issue->id],
        ]);

        $response->assertSessionHasErrors('action');
        $this->assertSame(Issue::STATUS_OPEN, $issue->fresh()->status);
    }

    public function test_an_empty_selection_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'acknowledge',
            'ids' => [],
        ]);

        $response->assertSessionHasErrors('ids');
    }

    public function test_unknown_issue_ids_are_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'acknowledge',
            'ids' => [999999],
        ]);

        $response->assertSessionHasErrors('ids.0');
    }

    public function test_soft_deleted_issues_cannot_be_bulk_transitioned(): void
    {
        $issue = Issue::factory()->create(['status' => Issue::STATUS_OPEN]);
        $issue->delete();

        $response = $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'acknowledge',
            'ids' => [$issue->id],
        ]);

        $response->assertSessionHasErrors('ids.0');
    }

    public function test_over_long_resolution_notes_are_rejected(): void
    {
        $issue = Issue::factory()->create(['status' => Issue::STATUS_OPEN]);

        $response = $this->actingAs($this->admin)->post('/admin/issues/bulk', [
            'action' => 'resolve',
            'ids' => [$issue->id],
            'resolution_notes' => str_repeat('x', 2001),
        ]);

        $response->assertSessionHasErrors('resolution_notes');
        $this->assertSame(Issue::STATUS_OPEN, $issue->fresh()->status);
    }

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_guest_cannot_bulk_transition_issues(): void
    {
        $issue = Issue::factory()->create(['status' => Issue::STATUS_OPEN]);

        $response = $this->post('/admin/issues/bulk', [
            'action' => 'acknowledge',
            'ids' => [$issue->id],
        ]);

        $response->assertRedirect('/login');
        $this->assertSame(Issue::STATUS_OPEN, $issue->fresh()->status);
    }

    public function test_operator_cannot_bulk_transition_issues(): void
    {
        $issue = Issue::factory()->create(['status' => Issue::STATUS_OPEN]);

        $response = $this->actingAs($this->operator)->post('/admin/issues/bulk', [
            'action' => 'acknowledge',
            'ids' => [$issue->id],
        ]);

        $response->assertStatus(403);
        $this->assertSame(Issue::STATUS_OPEN, $issue->fresh()->status);
    }
}
