<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineWorkstationApiTest extends TestCase
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

    private function authAdmin() { return $this->withHeader('Authorization', "Bearer {$this->adminToken}"); }
    private function authOperator() { return $this->withHeader('Authorization', "Bearer {$this->operatorToken}"); }
    private function authSupervisor() { return $this->withHeader('Authorization', "Bearer {$this->supervisorToken}"); }

    // ── GET /api/v1/lines ────────────────────────────────────────────────────

    public function test_admin_lists_active_lines_by_default(): void
    {
        Line::factory()->create(['is_active' => true]);
        Line::factory()->create(['is_active' => false]);

        $response = $this->authAdmin()->getJson('/api/v1/lines');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_can_include_inactive_lines(): void
    {
        Line::factory()->create(['is_active' => true]);
        Line::factory()->create(['is_active' => false]);

        $response = $this->authAdmin()->getJson('/api/v1/lines?include_inactive=1');
        $this->assertCount(2, $response->json('data'));
    }

    public function test_operator_only_sees_assigned_lines(): void
    {
        $assigned = Line::factory()->create();
        Line::factory()->create(); // unassigned
        $this->operator->lines()->attach($assigned->id);

        $response = $this->authOperator()->getJson('/api/v1/lines');
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($assigned->id, $response->json('data.0.id'));
    }

    public function test_lines_search_q_filters_by_name(): void
    {
        Line::factory()->create(['name' => 'Assembly Alpha']);
        Line::factory()->create(['name' => 'Welding Bravo']);

        $names = collect($this->authAdmin()->getJson('/api/v1/lines?q=alpha')->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Assembly Alpha'));
        $this->assertFalse($names->contains('Welding Bravo'));
    }

    // ── POST /api/v1/lines ───────────────────────────────────────────────────

    public function test_admin_can_create_line(): void
    {
        $response = $this->authAdmin()->postJson('/api/v1/lines', [
            'code' => 'LINE-1',
            'name' => 'Assembly 1',
            'is_active' => true,
        ]);
        $response->assertStatus(201)->assertJsonPath('data.code', 'LINE-1');
        $this->assertDatabaseHas('lines', ['code' => 'LINE-1']);
    }

    public function test_create_line_requires_unique_code(): void
    {
        Line::factory()->create(['code' => 'DUP']);
        $this->authAdmin()->postJson('/api/v1/lines', [
            'code' => 'DUP', 'name' => 'X',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_supervisor_cannot_create_line(): void
    {
        $this->authSupervisor()->postJson('/api/v1/lines', [
            'code' => 'X', 'name' => 'X',
        ])->assertStatus(403);
    }

    // ── PATCH /api/v1/lines/{id} ─────────────────────────────────────────────

    public function test_admin_can_update_line(): void
    {
        $line = Line::factory()->create(['name' => 'Old']);
        $this->authAdmin()->patchJson("/api/v1/lines/{$line->id}", [
            'name' => 'New Name',
        ])->assertStatus(200);
        $this->assertDatabaseHas('lines', ['id' => $line->id, 'name' => 'New Name']);
    }

    public function test_admin_can_toggle_active(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/lines/{$line->id}/toggle-active")->assertStatus(200);
        $this->assertFalse($line->fresh()->is_active);
    }

    // ── DELETE /api/v1/lines/{id} ────────────────────────────────────────────

    public function test_admin_can_delete_empty_line(): void
    {
        $line = Line::factory()->create();
        $this->authAdmin()->deleteJson("/api/v1/lines/{$line->id}")->assertStatus(200);
        $this->assertDatabaseMissing('lines', ['id' => $line->id]);
    }

    public function test_cannot_delete_line_with_work_orders(): void
    {
        $line = Line::factory()->create();
        \App\Models\WorkOrder::factory()->create(['line_id' => $line->id]);
        $this->authAdmin()->deleteJson("/api/v1/lines/{$line->id}")->assertStatus(422);
        $this->assertDatabaseHas('lines', ['id' => $line->id]);
    }

    // ── User pivot ───────────────────────────────────────────────────────────

    public function test_admin_can_sync_line_users(): void
    {
        $line = Line::factory()->create();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->authAdmin()->postJson("/api/v1/lines/{$line->id}/users", [
            'user_ids' => [$u1->id, $u2->id],
        ])->assertStatus(200);

        $this->assertEqualsCanonicalizing(
            [$u1->id, $u2->id],
            $line->fresh()->users->pluck('id')->all()
        );
    }

    public function test_admin_can_unassign_user(): void
    {
        $line = Line::factory()->create();
        $u = User::factory()->create();
        $line->users()->attach($u->id);

        $this->authAdmin()->deleteJson("/api/v1/lines/{$line->id}/users/{$u->id}")
            ->assertStatus(200);
        $this->assertCount(0, $line->fresh()->users);
    }

    public function test_operator_cannot_modify_line_users(): void
    {
        $line = Line::factory()->create();
        $u = User::factory()->create();
        $this->authOperator()->postJson("/api/v1/lines/{$line->id}/users", [
            'user_ids' => [$u->id],
        ])->assertStatus(403);
    }

    // ── Product types pivot ──────────────────────────────────────────────────

    public function test_admin_can_sync_line_product_types(): void
    {
        $line = Line::factory()->create();
        $pt1 = ProductType::factory()->create();
        $pt2 = ProductType::factory()->create();

        $this->authAdmin()->postJson("/api/v1/lines/{$line->id}/product-types", [
            'product_type_ids' => [$pt1->id, $pt2->id],
        ])->assertStatus(200);

        $this->assertEqualsCanonicalizing(
            [$pt1->id, $pt2->id],
            $line->fresh()->productTypes->pluck('id')->all()
        );
    }

    // ── Workstations ─────────────────────────────────────────────────────────

    public function test_admin_can_create_workstation(): void
    {
        $line = Line::factory()->create();

        $response = $this->authAdmin()->postJson("/api/v1/lines/{$line->id}/workstations", [
            'code' => 'WS-1',
            'name' => 'Station 1',
            'is_active' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.code', 'WS-1');
        $this->assertDatabaseHas('workstations', ['code' => 'WS-1', 'line_id' => $line->id]);
    }

    public function test_workstation_create_requires_unique_code(): void
    {
        $line = Line::factory()->create();
        Workstation::factory()->create(['code' => 'WS-DUP', 'line_id' => $line->id]);

        $this->authAdmin()->postJson("/api/v1/lines/{$line->id}/workstations", [
            'code' => 'WS-DUP', 'name' => 'X',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_admin_can_update_workstation(): void
    {
        $line = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $line->id, 'name' => 'Old']);
        $this->authAdmin()->patchJson("/api/v1/workstations/{$ws->id}", [
            'name' => 'New',
        ])->assertStatus(200);
        $this->assertDatabaseHas('workstations', ['id' => $ws->id, 'name' => 'New']);
    }

    public function test_admin_can_delete_workstation(): void
    {
        $line = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $line->id]);
        $this->authAdmin()->deleteJson("/api/v1/workstations/{$ws->id}")->assertStatus(200);
        $this->assertDatabaseMissing('workstations', ['id' => $ws->id]);
    }

    public function test_admin_can_toggle_workstation_active(): void
    {
        $line = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $line->id, 'is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/workstations/{$ws->id}/toggle-active")
            ->assertStatus(200);
        $this->assertFalse($ws->fresh()->is_active);
    }

    public function test_operator_cannot_create_workstation(): void
    {
        $line = Line::factory()->create();
        $this->authOperator()->postJson("/api/v1/lines/{$line->id}/workstations", [
            'code' => 'X', 'name' => 'X',
        ])->assertStatus(403);
    }

    public function test_workstation_list_returns_active_only_by_default(): void
    {
        $line = Line::factory()->create();
        Workstation::factory()->create(['line_id' => $line->id, 'is_active' => true]);
        Workstation::factory()->create(['line_id' => $line->id, 'is_active' => false]);

        $response = $this->authAdmin()->getJson("/api/v1/lines/{$line->id}/workstations");
        $this->assertCount(1, $response->json('data'));
    }
}
