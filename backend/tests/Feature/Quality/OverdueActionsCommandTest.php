<?php

namespace Tests\Feature\Quality;

use App\Models\Issue;
use App\Models\IssueAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The quality:notify-overdue-actions command (#11) flags outstanding actions
 * whose due date has passed. It is naturally idempotent — each run reports the
 * current overdue set.
 */
class OverdueActionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_overdue_actions(): void
    {
        $issue = Issue::factory()->create();
        IssueAction::factory()->overdue()->create(['issue_id' => $issue->id, 'title' => 'Replace tooling']);

        $this->artisan('quality:notify-overdue-actions')
            ->expectsOutputToContain('Replace tooling')
            ->assertSuccessful();
    }

    public function test_command_is_clean_when_nothing_is_overdue(): void
    {
        $issue = Issue::factory()->create();
        IssueAction::factory()->create(['issue_id' => $issue->id, 'due_date' => now()->addWeek()->toDateString()]);

        $this->artisan('quality:notify-overdue-actions')
            ->expectsOutput('No overdue actions.')
            ->assertSuccessful();
    }

    public function test_overdue_scope_excludes_verified_and_future(): void
    {
        $issue = Issue::factory()->create();
        $overdue = IssueAction::factory()->overdue()->create(['issue_id' => $issue->id]);
        IssueAction::factory()->verified()->create(['issue_id' => $issue->id, 'due_date' => now()->subWeek()->toDateString()]);
        IssueAction::factory()->create(['issue_id' => $issue->id, 'due_date' => now()->addWeek()->toDateString()]);

        $ids = IssueAction::overdue()->pluck('id')->all();
        $this->assertSame([$overdue->id], $ids);
    }

    public function test_containment_action_type_is_accepted(): void
    {
        $issue = Issue::factory()->create();

        $action = IssueAction::factory()->containment()->create(['issue_id' => $issue->id]);

        $this->assertSame(IssueAction::TYPE_CONTAINMENT, $action->type);
        $this->assertContains(IssueAction::TYPE_CONTAINMENT, IssueAction::TYPES);
    }
}
