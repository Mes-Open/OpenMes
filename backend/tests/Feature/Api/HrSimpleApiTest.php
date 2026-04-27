<?php

namespace Tests\Feature\Api;

use App\Models\Crew;
use App\Models\Skill;
use App\Models\User;
use App\Models\WageGroup;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrSimpleApiTest extends TestCase
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

    private function authAdmin() { return $this->withHeader('Authorization', "Bearer {$this->adminToken}"); }
    private function authOperator() { return $this->withHeader('Authorization', "Bearer {$this->operatorToken}"); }

    // ── Skills ──────────────────────────────────────────────────────────────

    public function test_skills_list(): void
    {
        Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $r = $this->authOperator()->getJson('/api/v1/skills');
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data'));
    }

    public function test_admin_can_create_skill(): void
    {
        $this->authAdmin()->postJson('/api/v1/skills', [
            'code' => 'PAINT', 'name' => 'Painting',
        ])->assertStatus(201);
        $this->assertDatabaseHas('skills', ['code' => 'PAINT']);
    }

    public function test_operator_cannot_create_skill(): void
    {
        $this->authOperator()->postJson('/api/v1/skills', [
            'code' => 'X', 'name' => 'X',
        ])->assertStatus(403);
    }

    public function test_skill_unique_code(): void
    {
        Skill::create(['code' => 'DUP', 'name' => 'X']);
        $this->authAdmin()->postJson('/api/v1/skills', [
            'code' => 'DUP', 'name' => 'Y',
        ])->assertStatus(422);
    }

    public function test_admin_can_update_skill(): void
    {
        $s = Skill::create(['code' => 'X', 'name' => 'Old']);
        $this->authAdmin()->patchJson("/api/v1/skills/{$s->id}", ['name' => 'New'])
            ->assertStatus(200);
        $this->assertDatabaseHas('skills', ['id' => $s->id, 'name' => 'New']);
    }

    public function test_admin_can_delete_unused_skill(): void
    {
        $s = Skill::create(['code' => 'X', 'name' => 'X']);
        $this->authAdmin()->deleteJson("/api/v1/skills/{$s->id}")->assertStatus(200);
    }

    public function test_cannot_delete_skill_assigned_to_worker(): void
    {
        $s = Skill::create(['code' => 'X', 'name' => 'X']);
        $worker = Worker::create(['code' => 'W1', 'name' => 'Test', 'is_active' => true]);
        $worker->skills()->attach($s->id, ['level' => 3]);

        $this->authAdmin()->deleteJson("/api/v1/skills/{$s->id}")->assertStatus(422);
    }

    // ── Wage Groups ─────────────────────────────────────────────────────────

    public function test_wage_groups_list_active_only_by_default(): void
    {
        WageGroup::create(['code' => 'WG1', 'name' => 'Active', 'is_active' => true]);
        WageGroup::create(['code' => 'WG2', 'name' => 'Inactive', 'is_active' => false]);
        $r = $this->authOperator()->getJson('/api/v1/wage-groups');
        $this->assertCount(1, $r->json('data'));
    }

    public function test_admin_can_create_wage_group(): void
    {
        $this->authAdmin()->postJson('/api/v1/wage-groups', [
            'code' => 'JR',
            'name' => 'Junior',
            'base_hourly_rate' => '25.5000',
            'currency' => 'EUR',
        ])->assertStatus(201);
        $this->assertDatabaseHas('wage_groups', ['code' => 'JR']);
    }

    public function test_admin_can_toggle_wage_group_active(): void
    {
        $wg = WageGroup::create(['code' => 'X', 'name' => 'X', 'is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/wage-groups/{$wg->id}/toggle-active")
            ->assertStatus(200);
        $this->assertFalse($wg->fresh()->is_active);
    }

    public function test_cannot_delete_wage_group_with_workers(): void
    {
        $wg = WageGroup::create(['code' => 'X', 'name' => 'X', 'is_active' => true]);
        Worker::create(['code' => 'W', 'name' => 'W', 'wage_group_id' => $wg->id, 'is_active' => true]);
        $this->authAdmin()->deleteJson("/api/v1/wage-groups/{$wg->id}")->assertStatus(422);
    }

    // ── Crews ───────────────────────────────────────────────────────────────

    public function test_admin_can_create_crew(): void
    {
        $this->authAdmin()->postJson('/api/v1/crews', [
            'code' => 'CREW-A', 'name' => 'Day Shift A',
        ])->assertStatus(201);
        $this->assertDatabaseHas('crews', ['code' => 'CREW-A']);
    }

    public function test_crew_can_have_leader(): void
    {
        $leader = User::factory()->create();
        $r = $this->authAdmin()->postJson('/api/v1/crews', [
            'code' => 'CREW-B', 'name' => 'Crew B', 'leader_id' => $leader->id,
        ]);
        $r->assertStatus(201)->assertJsonPath('data.leader.id', $leader->id);
    }

    public function test_crews_filters_active_only_by_default(): void
    {
        Crew::create(['code' => 'A', 'name' => 'A', 'is_active' => true]);
        Crew::create(['code' => 'B', 'name' => 'B', 'is_active' => false]);
        $this->assertCount(1, $this->authOperator()->getJson('/api/v1/crews')->json('data'));
    }

    public function test_cannot_delete_crew_with_workers(): void
    {
        $crew = Crew::create(['code' => 'C', 'name' => 'C', 'is_active' => true]);
        Worker::create(['code' => 'W', 'name' => 'W', 'crew_id' => $crew->id, 'is_active' => true]);
        $this->authAdmin()->deleteJson("/api/v1/crews/{$crew->id}")->assertStatus(422);
    }

    public function test_admin_can_get_crew_workers(): void
    {
        $crew = Crew::create(['code' => 'C', 'name' => 'C', 'is_active' => true]);
        Worker::create(['code' => 'W1', 'name' => 'Alice', 'crew_id' => $crew->id, 'is_active' => true]);

        $r = $this->authAdmin()->getJson("/api/v1/crews/{$crew->id}/workers");
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data'));
    }

    public function test_operator_cannot_create_crew(): void
    {
        $this->authOperator()->postJson('/api/v1/crews', [
            'code' => 'X', 'name' => 'X',
        ])->assertStatus(403);
    }
}
