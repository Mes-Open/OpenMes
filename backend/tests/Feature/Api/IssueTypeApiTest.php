<?php

namespace Tests\Feature\Api;

use App\Models\IssueType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $operator;
    protected string $adminToken;
    protected string $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;
    }

    // ── GET /api/v1/issue-types ──────────────────────────────────────────────

    public function test_any_authenticated_user_can_list_issue_types(): void
    {
        IssueType::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
            ->getJson('/api/v1/issue-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'severity', 'is_blocking'],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_list_issue_types(): void
    {
        $response = $this->getJson('/api/v1/issue-types');

        $response->assertStatus(401);
    }

    // ── GET /api/v1/issue-types/{id} ─────────────────────────────────────────

    public function test_can_get_single_issue_type(): void
    {
        $issueType = IssueType::factory()->create([
            'code'        => 'MACH_FAIL',
            'name'        => 'Machine Failure',
            'severity'    => 'CRITICAL',
            'is_blocking' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/issue-types/{$issueType->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id'          => $issueType->id,
                    'code'        => 'MACH_FAIL',
                    'name'        => 'Machine Failure',
                    'is_blocking' => true,
                ],
            ]);
    }

    public function test_get_nonexistent_issue_type_returns_404(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/issue-types/999999');

        $response->assertStatus(404);
    }

    // ── POST /api/v1/issue-types ─────────────────────────────────────────────

    public function test_admin_can_create_issue_type(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/issue-types', [
                'code'        => 'NEW_TYPE',
                'name'        => 'New Issue Type',
                'severity'    => 'HIGH',
                'is_blocking' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'code', 'name', 'severity', 'is_blocking'],
            ]);

        $this->assertDatabaseHas('issue_types', [
            'code' => 'NEW_TYPE',
            'name' => 'New Issue Type',
        ]);
    }

    public function test_operator_cannot_create_issue_type(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
            ->postJson('/api/v1/issue-types', [
                'code'     => 'NEW_TYPE',
                'name'     => 'New Issue Type',
                'severity' => 'HIGH',
            ]);

        $response->assertStatus(403);
    }

    public function test_create_issue_type_requires_code_and_name(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/issue-types', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_create_issue_type_requires_unique_code(): void
    {
        IssueType::factory()->create(['code' => 'EXISTING']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/issue-types', [
                'code'     => 'EXISTING',
                'name'     => 'Duplicate',
                'severity' => 'LOW',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    // ── PATCH /api/v1/issue-types/{id} ───────────────────────────────────────

    public function test_admin_can_update_issue_type(): void
    {
        $issueType = IssueType::factory()->create(['name' => 'Old Name']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/issue-types/{$issueType->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('issue_types', [
            'id'   => $issueType->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_operator_cannot_update_issue_type(): void
    {
        $issueType = IssueType::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
            ->patchJson("/api/v1/issue-types/{$issueType->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(403);
    }

    // ── DELETE /api/v1/issue-types/{id} ─────────────────────────────────────

    public function test_admin_can_delete_issue_type(): void
    {
        $issueType = IssueType::factory()->create(['is_active' => true]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/v1/issue-types/{$issueType->id}");

        $response->assertStatus(200);

        // Controller soft-deletes by setting is_active = false
        $this->assertDatabaseHas('issue_types', ['id' => $issueType->id, 'is_active' => false]);
    }

    public function test_operator_cannot_delete_issue_type(): void
    {
        $issueType = IssueType::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
            ->deleteJson("/api/v1/issue-types/{$issueType->id}");

        $response->assertStatus(403);
    }
}
