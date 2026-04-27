<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $supervisor;
    protected User $operator;
    protected string $adminToken;
    protected string $supervisorToken;
    protected string $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->supervisorToken = $this->supervisor->createToken('test')->plainTextToken;

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;
    }

    private function authAdmin()
    {
        return $this->withHeader('Authorization', "Bearer {$this->adminToken}");
    }

    private function authOperator()
    {
        return $this->withHeader('Authorization', "Bearer {$this->operatorToken}");
    }

    // ── GET /api/v1/users ────────────────────────────────────────────────────

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(2)->create();

        $response = $this->authAdmin()->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'username', 'email', 'roles', 'lines'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_operator_cannot_list_users(): void
    {
        $response = $this->authOperator()->getJson('/api/v1/users');
        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users')->assertStatus(401);
    }

    public function test_users_can_be_filtered_by_role(): void
    {
        User::factory()->count(2)->create()->each->assignRole('Operator');

        $response = $this->authAdmin()->getJson('/api/v1/users?role=Operator');

        $response->assertStatus(200);
        $usernames = collect($response->json('data'))->pluck('username');
        $this->assertFalse($usernames->contains($this->admin->username));
    }

    public function test_users_can_be_searched_by_q(): void
    {
        User::factory()->create(['username' => 'alice_engineer']);
        User::factory()->create(['username' => 'bob_assembler']);

        $response = $this->authAdmin()->getJson('/api/v1/users?q=alice');

        $response->assertStatus(200);
        $usernames = collect($response->json('data'))->pluck('username');
        $this->assertTrue($usernames->contains('alice_engineer'));
        $this->assertFalse($usernames->contains('bob_assembler'));
    }

    // ── GET /api/v1/users/{id} ───────────────────────────────────────────────

    public function test_admin_can_show_user(): void
    {
        $user = User::factory()->create();

        $this->authAdmin()->getJson("/api/v1/users/{$user->id}")
            ->assertStatus(200)
            ->assertJson(['data' => ['id' => $user->id, 'username' => $user->username]]);
    }

    public function test_show_nonexistent_user_returns_404(): void
    {
        $this->authAdmin()->getJson('/api/v1/users/999999')->assertStatus(404);
    }

    // ── POST /api/v1/users ───────────────────────────────────────────────────

    public function test_admin_can_create_user_with_role_and_lines(): void
    {
        $line = Line::factory()->create();

        $response = $this->authAdmin()->postJson('/api/v1/users', [
            'name' => 'New Operator',
            'username' => 'newop',
            'email' => 'newop@example.com',
            'password' => 'Sup3rSecret!',
            'account_type' => 'user',
            'role' => 'Operator',
            'line_ids' => [$line->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.username', 'newop')
            ->assertJsonPath('data.account_type', 'user');

        $this->assertDatabaseHas('users', ['username' => 'newop', 'email' => 'newop@example.com']);

        $created = User::where('username', 'newop')->first();
        $this->assertTrue($created->hasRole('Operator'));
        $this->assertEquals([$line->id], $created->lines->pluck('id')->all());
    }

    public function test_create_user_requires_unique_username(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->authAdmin()->postJson('/api/v1/users', [
            'name' => 'X',
            'username' => 'taken',
            'email' => 'x@example.com',
            'password' => 'Sup3rSecret!',
            'account_type' => 'user',
            'role' => 'Operator',
        ])->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    public function test_create_user_requires_role_when_account_type_user(): void
    {
        $this->authAdmin()->postJson('/api/v1/users', [
            'name' => 'X',
            'username' => 'noroleuser',
            'email' => 'x@example.com',
            'password' => 'Sup3rSecret!',
            'account_type' => 'user',
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);
    }

    public function test_operator_cannot_create_user(): void
    {
        $this->authOperator()->postJson('/api/v1/users', [
            'name' => 'X', 'username' => 'x', 'email' => 'x@example.com',
            'password' => 'Sup3rSecret!', 'account_type' => 'user', 'role' => 'Operator',
        ])->assertStatus(403);
    }

    // ── PATCH /api/v1/users/{id} ─────────────────────────────────────────────

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Operator');

        $response = $this->authAdmin()->patchJson("/api/v1/users/{$user->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_change_user_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Operator');

        $this->authAdmin()->patchJson("/api/v1/users/{$user->id}", [
            'role' => 'Supervisor',
            'account_type' => 'user',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->hasRole('Supervisor'));
        $this->assertFalse($user->hasRole('Operator'));
    }

    public function test_admin_can_sync_user_lines_via_update(): void
    {
        $user = User::factory()->create();
        $line1 = Line::factory()->create();
        $line2 = Line::factory()->create();

        $this->authAdmin()->patchJson("/api/v1/users/{$user->id}", [
            'line_ids' => [$line1->id, $line2->id],
        ])->assertStatus(200);

        $this->assertEqualsCanonicalizing(
            [$line1->id, $line2->id],
            $user->fresh()->lines->pluck('id')->all()
        );
    }

    public function test_operator_cannot_update_user(): void
    {
        $user = User::factory()->create();

        $this->authOperator()->patchJson("/api/v1/users/{$user->id}", [
            'name' => 'Hacked',
        ])->assertStatus(403);
    }

    // ── DELETE /api/v1/users/{id} ────────────────────────────────────────────

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->create();

        $this->authAdmin()->deleteJson("/api/v1/users/{$user->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->authAdmin()->deleteJson("/api/v1/users/{$this->admin->id}")
            ->assertStatus(403);
    }

    // ── POST /api/v1/users/{id}/reset-password ───────────────────────────────

    public function test_admin_can_reset_user_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpass123')]);
        $user->createToken('test');

        $this->assertEquals(1, PersonalAccessToken::where('tokenable_id', $user->id)->count());

        $this->authAdmin()->postJson("/api/v1/users/{$user->id}/reset-password", [
            'password' => 'NewSecret9!',
            'force_password_change' => true,
        ])->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret9!', $user->password));
        $this->assertTrue($user->force_password_change);

        // Pre-existing tokens are revoked at the DB level
        $this->assertEquals(0, PersonalAccessToken::where('tokenable_id', $user->id)->count());
    }

    public function test_operator_cannot_reset_password(): void
    {
        $user = User::factory()->create();

        $this->authOperator()->postJson("/api/v1/users/{$user->id}/reset-password", [
            'password' => 'NewSecret9!',
        ])->assertStatus(403);
    }

    // ── GET / POST /api/v1/users/{id}/lines ──────────────────────────────────

    public function test_admin_can_list_user_lines(): void
    {
        $user = User::factory()->create();
        $line = Line::factory()->create();
        $user->lines()->sync([$line->id]);

        $this->authAdmin()->getJson("/api/v1/users/{$user->id}/lines")
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $line->id);
    }

    public function test_admin_can_sync_user_lines(): void
    {
        $user = User::factory()->create();
        $line1 = Line::factory()->create();
        $line2 = Line::factory()->create();

        $this->authAdmin()->postJson("/api/v1/users/{$user->id}/lines", [
            'line_ids' => [$line1->id, $line2->id],
        ])->assertStatus(200);

        $this->assertEqualsCanonicalizing(
            [$line1->id, $line2->id],
            $user->fresh()->lines->pluck('id')->all()
        );
    }

    // ── GET /api/v1/roles ────────────────────────────────────────────────────

    public function test_admin_can_list_roles(): void
    {
        $this->authAdmin()->getJson('/api/v1/roles')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_operator_cannot_list_roles(): void
    {
        $this->authOperator()->getJson('/api/v1/roles')->assertStatus(403);
    }
}
