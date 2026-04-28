<?php

namespace Tests\Feature\Api;

use App\Models\Division;
use App\Models\Factory;
use App\Models\Line;
use App\Models\LineStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgStructureApiTest extends TestCase
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

    // ── Factories ─────────────────────────────────────────────────────────

    public function test_admin_can_create_factory(): void
    {
        $r = $this->authAdmin()->postJson('/api/v1/factories', [
            'code' => 'F1', 'name' => 'Main plant',
        ]);
        $r->assertStatus(201);
        $this->assertDatabaseHas('factories', ['code' => 'F1']);
    }

    public function test_anyone_can_list_active_factories(): void
    {
        Factory::create(['code' => 'A', 'name' => 'A', 'is_active' => true]);
        Factory::create(['code' => 'B', 'name' => 'B', 'is_active' => false]);
        $r = $this->authOperator()->getJson('/api/v1/factories');
        $this->assertCount(1, $r->json('data'));
    }

    public function test_cannot_delete_factory_with_divisions(): void
    {
        $f = Factory::create(['code' => 'F', 'name' => 'F', 'is_active' => true]);
        Division::create(['factory_id' => $f->id, 'code' => 'D', 'name' => 'D', 'is_active' => true]);
        $this->authAdmin()->deleteJson("/api/v1/factories/{$f->id}")->assertStatus(422);
    }

    public function test_operator_cannot_create_factory(): void
    {
        $this->authOperator()->postJson('/api/v1/factories', [
            'code' => 'X', 'name' => 'X',
        ])->assertStatus(403);
    }

    public function test_admin_can_toggle_factory(): void
    {
        $f = Factory::create(['code' => 'F', 'name' => 'F', 'is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/factories/{$f->id}/toggle-active")->assertStatus(200);
        $this->assertFalse($f->fresh()->is_active);
    }

    // ── Divisions ─────────────────────────────────────────────────────────

    public function test_admin_can_create_division(): void
    {
        $f = Factory::create(['code' => 'F', 'name' => 'F', 'is_active' => true]);
        $r = $this->authAdmin()->postJson("/api/v1/factories/{$f->id}/divisions", [
            'code' => 'D1', 'name' => 'Assembly Hall',
        ]);
        $r->assertStatus(201);
        $this->assertDatabaseHas('divisions', ['code' => 'D1', 'factory_id' => $f->id]);
    }

    public function test_division_unique_code(): void
    {
        $f = Factory::create(['code' => 'F', 'name' => 'F', 'is_active' => true]);
        Division::create(['factory_id' => $f->id, 'code' => 'DUP', 'name' => 'X', 'is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/factories/{$f->id}/divisions", [
            'code' => 'DUP', 'name' => 'Y',
        ])->assertStatus(422);
    }

    public function test_cannot_delete_division_with_lines(): void
    {
        $f = Factory::create(['code' => 'F', 'name' => 'F', 'is_active' => true]);
        $d = Division::create(['factory_id' => $f->id, 'code' => 'D', 'name' => 'D', 'is_active' => true]);
        Line::factory()->create(['division_id' => $d->id]);
        $this->authAdmin()->deleteJson("/api/v1/divisions/{$d->id}")->assertStatus(422);
    }

    // ── Line statuses ─────────────────────────────────────────────────────

    public function test_admin_can_create_line_status(): void
    {
        $line = Line::factory()->create();
        $r = $this->authAdmin()->postJson("/api/v1/lines/{$line->id}/statuses", [
            'name' => 'Setup',
            'color' => '#3b82f6',
        ]);
        $r->assertStatus(201)->assertJsonPath('data.name', 'Setup');
    }

    public function test_only_one_default_status(): void
    {
        $line = Line::factory()->create();
        $s1 = LineStatus::create(['line_id' => $line->id, 'name' => 'A', 'is_default' => true, 'sort_order' => 1]);
        $this->authAdmin()->postJson("/api/v1/lines/{$line->id}/statuses", [
            'name' => 'B', 'is_default' => true,
        ])->assertStatus(201);
        $this->assertFalse($s1->fresh()->is_default);
    }

    public function test_admin_can_reorder_line_statuses(): void
    {
        $line = Line::factory()->create();
        $a = LineStatus::create(['line_id' => $line->id, 'name' => 'A', 'sort_order' => 1]);
        $b = LineStatus::create(['line_id' => $line->id, 'name' => 'B', 'sort_order' => 2]);
        $c = LineStatus::create(['line_id' => $line->id, 'name' => 'C', 'sort_order' => 3]);

        $this->authAdmin()->postJson("/api/v1/lines/{$line->id}/statuses/reorder", [
            'status_ids' => [$c->id, $a->id, $b->id],
        ])->assertStatus(200);

        $this->assertEquals(1, $c->fresh()->sort_order);
        $this->assertEquals(2, $a->fresh()->sort_order);
        $this->assertEquals(3, $b->fresh()->sort_order);
    }

    public function test_reorder_rejects_alien_status(): void
    {
        $l1 = Line::factory()->create();
        $l2 = Line::factory()->create();
        $a = LineStatus::create(['line_id' => $l1->id, 'name' => 'A', 'sort_order' => 1]);
        $alien = LineStatus::create(['line_id' => $l2->id, 'name' => 'X', 'sort_order' => 1]);

        $this->authAdmin()->postJson("/api/v1/lines/{$l1->id}/statuses/reorder", [
            'status_ids' => [$a->id, $alien->id],
        ])->assertStatus(422);
    }

    public function test_admin_can_delete_line_status(): void
    {
        $line = Line::factory()->create();
        $s = LineStatus::create(['line_id' => $line->id, 'name' => 'X', 'sort_order' => 1]);
        $this->authAdmin()->deleteJson("/api/v1/line-statuses/{$s->id}")->assertStatus(200);
    }
}
