<?php

namespace Tests\Feature\Api;

use App\Models\Issue;
use App\Models\IssueType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/v1/issues/{issue}/resolve needs the `resolve issues` permission
 * (Supervisor and Admin by default), matching the web, where resolving only
 * exists on the supervisor and admin screens.
 */
class IssueResolveAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_operator_token_cannot_resolve_an_issue(): void
    {
        $issue = $this->openIssue();
        $operator = $this->userWithRole('Operator');
        $token = $operator->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/issues/{$issue->id}/resolve", ['resolution_notes' => 'Fixed'])
            ->assertForbidden();

        $issue->refresh();
        $this->assertSame(Issue::STATUS_OPEN, $issue->status);
        $this->assertNull($issue->resolved_at);
    }

    public function test_forbidden_operator_does_not_unblock_the_work_order(): void
    {
        $issue = $this->openIssue(blocking: true);
        $issue->workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);

        $this->actingAs($this->userWithRole('Operator'), 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/resolve", ['resolution_notes' => 'Fixed'])
            ->assertForbidden();

        $this->assertSame(WorkOrder::STATUS_BLOCKED, $issue->workOrder->fresh()->status);
    }

    public function test_supervisor_can_resolve_an_issue(): void
    {
        $issue = $this->openIssue();
        $token = $this->userWithRole('Supervisor')->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/issues/{$issue->id}/resolve", ['resolution_notes' => 'Fixed'])
            ->assertOk()
            ->assertJsonPath('data.status', Issue::STATUS_RESOLVED);

        $this->assertSame('Fixed', $issue->fresh()->resolution_notes);
    }

    public function test_admin_can_resolve_an_issue(): void
    {
        $issue = $this->openIssue();

        $this->actingAs($this->userWithRole('Admin'), 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/resolve", ['resolution_notes' => 'Fixed'])
            ->assertOk()
            ->assertJsonPath('data.status', Issue::STATUS_RESOLVED);
    }

    public function test_operator_granted_the_permission_can_resolve(): void
    {
        $issue = $this->openIssue();
        $operator = $this->userWithRole('Operator');
        $operator->givePermissionTo('resolve issues');

        $this->actingAs($operator, 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/resolve", ['resolution_notes' => 'Fixed'])
            ->assertOk();
    }

    public function test_guest_gets_401(): void
    {
        $issue = $this->openIssue();

        $this->postJson("/api/v1/issues/{$issue->id}/resolve", ['resolution_notes' => 'Fixed'])
            ->assertUnauthorized();

        $this->assertSame(Issue::STATUS_OPEN, $issue->fresh()->status);
    }

    public function test_missing_resolution_notes_returns_422_for_supervisor(): void
    {
        $issue = $this->openIssue();

        $this->actingAs($this->userWithRole('Supervisor'), 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/resolve", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('resolution_notes');
    }

    public function test_operator_is_refused_before_validation(): void
    {
        // Authorization runs first, so an operator learns nothing about the payload rules.
        $issue = $this->openIssue();

        $this->actingAs($this->userWithRole('Operator'), 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/resolve", [])
            ->assertForbidden();
    }

    public function test_resolving_an_already_resolved_issue_returns_422(): void
    {
        $issue = $this->openIssue();
        $issue->update(['status' => Issue::STATUS_RESOLVED, 'resolved_at' => now()]);

        $this->actingAs($this->userWithRole('Supervisor'), 'sanctum')
            ->postJson("/api/v1/issues/{$issue->id}/resolve", ['resolution_notes' => 'Again'])
            ->assertStatus(422);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function openIssue(bool $blocking = false): Issue
    {
        $type = IssueType::factory()->create(['is_blocking' => $blocking]);

        return Issue::factory()->create([
            'work_order_id' => WorkOrder::factory()->create()->id,
            'issue_type_id' => $type->id,
            'status' => Issue::STATUS_OPEN,
        ]);
    }
}
