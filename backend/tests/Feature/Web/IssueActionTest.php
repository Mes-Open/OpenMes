<?php

namespace Tests\Feature\Web;

use App\Models\Issue;
use App\Models\IssueAction;
use App\Models\User;
use App\Services\IssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Corrective/preventive actions (CAPA) on issues + the closure gate: an issue
 * can only be CLOSED once all of its actions are VERIFIED.
 */
class IssueActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_add_a_corrective_action(): void
    {
        $issue = Issue::factory()->create();

        $this->actingAs($this->admin)
            ->postJson("/admin/issues/{$issue->id}/actions", ['type' => 'corrective', 'title' => 'Fix the root cause'])
            ->assertStatus(201)
            ->assertJsonPath('actions.0.title', 'Fix the root cause');

        $this->assertDatabaseHas('issue_actions', [
            'issue_id' => $issue->id,
            'type' => 'corrective',
            'status' => 'open',
        ]);
    }

    public function test_action_runs_through_start_complete_verify(): void
    {
        $issue = Issue::factory()->create();
        $action = IssueAction::factory()->create(['issue_id' => $issue->id]);

        $this->actingAs($this->admin)->postJson("/admin/issues/actions/{$action->id}/start")
            ->assertOk()->assertJsonPath('actions.0.status', 'in_progress');
        $this->actingAs($this->admin)->postJson("/admin/issues/actions/{$action->id}/complete", ['notes' => 'done it'])
            ->assertOk()->assertJsonPath('actions.0.status', 'done');
        $this->actingAs($this->admin)->postJson("/admin/issues/actions/{$action->id}/verify")
            ->assertOk()->assertJsonPath('actions.0.status', 'verified');

        $action->refresh();
        $this->assertSame($this->admin->id, $action->verified_by_id);
        $this->assertNotNull($action->verified_at);
    }

    public function test_verifying_a_non_completed_action_is_rejected(): void
    {
        $issue = Issue::factory()->create();
        $action = IssueAction::factory()->create(['issue_id' => $issue->id, 'status' => 'open']);

        $this->actingAs($this->admin)->postJson("/admin/issues/actions/{$action->id}/verify")
            ->assertStatus(422);
        $this->assertSame('open', $action->fresh()->status);
    }

    public function test_issue_cannot_be_closed_while_an_action_is_unverified(): void
    {
        $issue = Issue::factory()->create(['status' => Issue::STATUS_RESOLVED, 'resolved_at' => now()]);
        IssueAction::factory()->create(['issue_id' => $issue->id, 'status' => 'done']);

        $this->actingAs($this->admin)->post("/admin/issues/{$issue->id}/close")
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(Issue::STATUS_RESOLVED, $issue->fresh()->status);
    }

    public function test_issue_closes_once_all_actions_are_verified(): void
    {
        $issue = Issue::factory()->create(['status' => Issue::STATUS_RESOLVED, 'resolved_at' => now()]);
        IssueAction::factory()->verified()->create(['issue_id' => $issue->id]);

        $this->actingAs($this->admin)->post("/admin/issues/{$issue->id}/close")
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(Issue::STATUS_CLOSED, $issue->fresh()->status);
    }

    public function test_close_issue_service_throws_on_unverified_actions(): void
    {
        $issue = Issue::factory()->create(['status' => Issue::STATUS_RESOLVED, 'resolved_at' => now()]);
        IssueAction::factory()->create(['issue_id' => $issue->id, 'status' => 'done']);

        $this->expectException(\DomainException::class);
        app(IssueService::class)->closeIssue($issue);
    }

    public function test_action_validation_rejects_missing_title_and_bad_type(): void
    {
        $issue = Issue::factory()->create();

        $this->actingAs($this->admin)
            ->postJson("/admin/issues/{$issue->id}/actions", ['type' => 'corrective'])
            ->assertStatus(422)->assertJsonValidationErrors('title');

        $this->actingAs($this->admin)
            ->postJson("/admin/issues/{$issue->id}/actions", ['type' => 'nonsense', 'title' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_destroying_an_action_soft_deletes_it(): void
    {
        $issue = Issue::factory()->create();
        $action = IssueAction::factory()->create(['issue_id' => $issue->id]);

        $this->actingAs($this->admin)->deleteJson("/admin/issues/actions/{$action->id}")->assertOk();
        $this->assertSoftDeleted('issue_actions', ['id' => $action->id]);
    }

    public function test_soft_deleting_an_issue_cascades_to_its_actions(): void
    {
        $issue = Issue::factory()->create();
        $action = IssueAction::factory()->create(['issue_id' => $issue->id]);

        $issue->delete();

        $this->assertSoftDeleted('issue_actions', ['id' => $action->id]);
    }

    public function test_guest_cannot_add_actions(): void
    {
        $issue = Issue::factory()->create();

        $this->postJson("/admin/issues/{$issue->id}/actions", ['type' => 'corrective', 'title' => 'x'])
            ->assertUnauthorized();
    }
}
