<?php

namespace Tests\Feature\Api;

use App\Models\Crew;
use App\Models\Skill;
use App\Models\User;
use App\Models\WageGroup;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerApiTest extends TestCase
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

    public function test_workers_list_paginated(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Worker::create(['code' => "W{$i}", 'name' => "Worker {$i}", 'is_active' => true]);
        }
        $r = $this->authOperator()->getJson('/api/v1/workers');
        $r->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);
        $this->assertCount(3, $r->json('data'));
    }

    public function test_workers_filter_by_crew(): void
    {
        $crew = Crew::create(['code' => 'C', 'name' => 'C', 'is_active' => true]);
        Worker::create(['code' => 'W1', 'name' => 'In crew', 'crew_id' => $crew->id, 'is_active' => true]);
        Worker::create(['code' => 'W2', 'name' => 'Solo', 'is_active' => true]);

        $r = $this->authAdmin()->getJson("/api/v1/workers?crew_id={$crew->id}");
        $this->assertCount(1, $r->json('data'));
    }

    public function test_workers_search_q(): void
    {
        Worker::create(['code' => 'W1', 'name' => 'Alice', 'is_active' => true]);
        Worker::create(['code' => 'W2', 'name' => 'Bob', 'is_active' => true]);

        $r = $this->authAdmin()->getJson('/api/v1/workers?q=alice');
        $this->assertCount(1, $r->json('data'));
    }

    public function test_admin_can_create_worker(): void
    {
        $crew = Crew::create(['code' => 'C', 'name' => 'C', 'is_active' => true]);
        $wg = WageGroup::create(['code' => 'WG', 'name' => 'WG', 'is_active' => true]);

        $r = $this->authAdmin()->postJson('/api/v1/workers', [
            'code' => 'W-1',
            'name' => 'Alice Engineer',
            'email' => 'alice@example.com',
            'crew_id' => $crew->id,
            'wage_group_id' => $wg->id,
        ]);
        $r->assertStatus(201)
            ->assertJsonPath('data.code', 'W-1')
            ->assertJsonPath('data.crew.id', $crew->id);
    }

    public function test_admin_can_create_worker_with_skills(): void
    {
        $s1 = Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $s2 = Skill::create(['code' => 'PAINT', 'name' => 'Painting']);

        $r = $this->authAdmin()->postJson('/api/v1/workers', [
            'code' => 'W-2',
            'name' => 'Bob',
            'skills' => [
                ['id' => $s1->id, 'level' => 5],
                ['id' => $s2->id, 'level' => 2],
            ],
        ]);
        $r->assertStatus(201);

        $worker = Worker::where('code', 'W-2')->first();
        $this->assertCount(2, $worker->skills);
        $this->assertEquals(5, $worker->skills->where('id', $s1->id)->first()->pivot->level);
    }

    public function test_create_requires_unique_code(): void
    {
        Worker::create(['code' => 'DUP', 'name' => 'X', 'is_active' => true]);
        $this->authAdmin()->postJson('/api/v1/workers', [
            'code' => 'DUP', 'name' => 'Y',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_admin_can_update_worker(): void
    {
        $w = Worker::create(['code' => 'W', 'name' => 'Old', 'is_active' => true]);
        $this->authAdmin()->patchJson("/api/v1/workers/{$w->id}", [
            'name' => 'New',
        ])->assertStatus(200);
        $this->assertDatabaseHas('workers', ['id' => $w->id, 'name' => 'New']);
    }

    public function test_admin_can_sync_skills(): void
    {
        $w = Worker::create(['code' => 'W', 'name' => 'X', 'is_active' => true]);
        $s1 = Skill::create(['code' => 'A', 'name' => 'A']);
        $s2 = Skill::create(['code' => 'B', 'name' => 'B']);

        $this->authAdmin()->postJson("/api/v1/workers/{$w->id}/skills", [
            'skills' => [
                ['id' => $s1->id, 'level' => 4],
                ['id' => $s2->id, 'level' => 3],
            ],
        ])->assertStatus(200);

        $this->assertEqualsCanonicalizing(
            [$s1->id, $s2->id],
            $w->fresh()->skills->pluck('id')->all()
        );
    }

    public function test_admin_can_delete_unlinked_worker(): void
    {
        $w = Worker::create(['code' => 'W', 'name' => 'X', 'is_active' => true]);
        $this->authAdmin()->deleteJson("/api/v1/workers/{$w->id}")->assertStatus(200);
        $this->assertDatabaseMissing('workers', ['id' => $w->id]);
    }

    public function test_cannot_delete_worker_linked_to_user(): void
    {
        $w = Worker::create(['code' => 'W', 'name' => 'X', 'is_active' => true]);
        $u = User::factory()->create(['worker_id' => $w->id]);

        $this->authAdmin()->deleteJson("/api/v1/workers/{$w->id}")->assertStatus(422);
    }

    public function test_operator_cannot_create_worker(): void
    {
        $this->authOperator()->postJson('/api/v1/workers', [
            'code' => 'X', 'name' => 'X',
        ])->assertStatus(403);
    }
}
