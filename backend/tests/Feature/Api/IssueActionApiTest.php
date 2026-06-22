<?php

namespace Tests\Feature\Api;

use App\Models\Issue;
use App\Models\IssueAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API issue-action endpoints (#11): the PUT /issue-actions/{id} status update
 * must go through the lifecycle (open → in_progress → done → verified), not
 * allow arbitrary jumps, and capture the audit stamps.
 */
class IssueActionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('Admin');
    }

    public function test_status_update_follows_the_lifecycle_and_stamps_audit(): void
    {
        $issue = Issue::factory()->create();
        $action = IssueAction::factory()->create(['issue_id' => $issue->id, 'status' => 'open']);

        // open → in_progress
        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/issue-actions/{$action->id}", ['status' => 'in_progress'])
            ->assertOk();
        $this->assertSame('in_progress', $action->fresh()->status);

        // in_progress → done stamps completed_by
        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/issue-actions/{$action->id}", ['status' => 'done'])
            ->assertOk();
        $action->refresh();
        $this->assertSame('done', $action->status);
        $this->assertSame($this->user->id, $action->completed_by_id);

        // done → verified stamps verified_by
        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/issue-actions/{$action->id}", ['status' => 'verified'])
            ->assertOk();
        $action->refresh();
        $this->assertSame('verified', $action->status);
        $this->assertSame($this->user->id, $action->verified_by_id);
    }

    public function test_illegal_status_jump_is_rejected(): void
    {
        $issue = Issue::factory()->create();
        $action = IssueAction::factory()->create(['issue_id' => $issue->id, 'status' => 'open']);

        // open → verified skips the lifecycle and must be refused.
        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/issue-actions/{$action->id}", ['status' => 'verified'])
            ->assertStatus(422);

        $this->assertSame('open', $action->fresh()->status);
    }

    public function test_field_edit_without_status_still_works(): void
    {
        $issue = Issue::factory()->create();
        $action = IssueAction::factory()->create(['issue_id' => $issue->id, 'title' => 'Old']);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/issue-actions/{$action->id}", ['title' => 'New title'])
            ->assertOk();

        $this->assertSame('New title', $action->fresh()->title);
    }
}
